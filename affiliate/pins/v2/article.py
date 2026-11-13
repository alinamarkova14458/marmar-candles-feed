"""Article pins for jalina.art blog posts (1000x1500), same visual family as template R (Alina, 12.11.2026):
"JALINA.ART" studio line on top, big Bodoni Moda title, short caps subline, large imagery from the post itself
(product images or Alina's own visuals / set elements), paper background, auto theme light/dark/blue by the imagery.
No plaques, no badges, no price, no "affiliate" text; the only signature is JALINA.ART.

  python3 article.py <preset|spec.json> [A|B|C ...] [--theme auto|light|dark|blue] [--render]
  python3 article.py presets                     list built-in presets (one per post)
  python3 article.py sheet [prefix]              contact sheet article-samples/_sheet.jpg (+ _sheet-236.jpg)

Variants:
  A  hero      one big image (photo card or cut-out, optional fx) in the middle, title + subline below
  B  stack     2-3 large overlapping, slightly tilted images under the title block
               (fx "scale": the same cut-out at its real print sizes for several DPI values, labelled)
  C  title-led big title (up to 3 lines) on top, one large cut-out element under it

Image refs in a spec: "/img/<file>" (jalina-blog/public/img), "set:<set-id>/<element-name>" (a PNG of Alina's set,
from the ZIP in jalina-factory/ready), or {"src": ref, "crop": [l, t, r, b]} (source pixels, to cut text off
product images). Spec keys: slug, title, sub, hero, hero_fx (checker-split | measure), stack, stack_fx (scale),
cutout, c_title, c_sub. --render writes article-samples/art-<slug>-<V>.jpg via `node shot.js`.
"""
import argparse, hashlib, io, json, os, re, shutil, subprocess, sys, zipfile, glob

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import gen  # noqa: E402  (set_elements, _lab, defringed, FONT_DIR, BLOG, FACTORY, CACHE, esc)

OUT = os.path.join(HERE, 'article-samples')
SLATE = '#7D8CAB'

# Built-in presets (facts checked against the posts / set files, 13.11.2026)
PRESETS = {
    'png-not-transparent': {
        'title': 'PNG Not Transparent?', 'sub': 'Guide · spot a fake checkerboard & fix it',
        'hero': 'set:05-pastel-christmas/watercolor-pink-striped-bauble', 'hero_fx': 'checker-split',
        'stack': [{'src': '/img/png-not-transparent-fake-vs-real.webp', 'crop': [40, 110, 580, 700]},
                  {'src': '/img/png-not-transparent-fake-vs-real.webp', 'crop': [620, 110, 1160, 700]},
                  {'src': '/img/png-not-transparent-pastel-christmas-light-dark.webp', 'crop': [0, 235, 1200, 800]}],
        'cutout': 'set:05-pastel-christmas/watercolor-pink-striped-bauble',
        'c_title': 'Is Your PNG Really Transparent?', 'c_sub': 'Guide · the 10-second check',
    },
    'christmas-gift-tags-printable': {
        'title': 'Printable Christmas Gift Tags', 'sub': '8 sets · print, Canva or cut',
        'hero': {'src': '/img/tag-kmarina.webp', 'crop': [600, 0, 1200, 690]},
        'stack': [{'src': '/img/tag-plaid.webp', 'crop': [330, 0, 1200, 800]},
                  {'src': '/img/tag-woodland.webp', 'crop': [60, 285, 640, 690]},
                  {'src': '/img/tag-svg.webp', 'crop': [90, 0, 1200, 760]}],
        'cutout': 'set:05-pastel-christmas/watercolor-gift-box-with-pine',
        'c_title': 'Christmas Gift Tags to Print', 'c_sub': '8 sets + printing & cutting tips',
    },
    'tools/print-size-calculator': {
        'title': 'Print Size & DPI Calculator', 'sub': 'Free tool · pixels to inches',
        'hero': 'set:05-pastel-christmas/watercolor-eucalyptus-christmas-wreath', 'hero_fx': 'measure',
        'stack': ['set:05-pastel-christmas/watercolor-vintage-truck-with-tree'], 'stack_fx': 'scale', 'b_sub': 'Free tool · one 3600 px file, 3 sizes',
        'cutout': 'set:05-pastel-christmas/watercolor-gingerbread-house',
        'c_title': 'How Big Can Your PNG Print?', 'c_sub': 'Free tool · pixels ÷ DPI = inches',
    },
}


# ---------- images ----------
def _set_png(ref):
    """'set:<sid>/<name>' -> (path of the cached 1000px PNG, original (w, h) in the ZIP)."""
    sid, name = ref[4:].split('/', 1)
    name = name if name.endswith('.png') else name + '.png'
    els = gen.set_elements(sid)
    e = next((e for e in els if e[0] == name), None)
    if not e:
        sys.exit(f'{name} not in set {sid}')
    from PIL import Image
    with zipfile.ZipFile(glob.glob(f'{gen.FACTORY}/{sid}/*.zip')[0]) as z:
        n = next(x for x in z.namelist() if x.endswith('/' + name) or x == name)
        size = Image.open(io.BytesIO(z.read(n))).size
    return e, size


def resolve(ref):
    """-> dict(path, ar, cut (True = transparent cut-out), size (original px for set elements))."""
    from PIL import Image
    if isinstance(ref, str):
        ref = {'src': ref}
    src = ref['src']
    if src.startswith('set:'):
        e, size = _set_png(src)
        return {'path': e[2], 'ar': e[3], 'cut': True, 'size': size, 'el': e, 'set': src[4:].split('/', 1)[0]}
    path = gen.BLOG + '/public' + src if src.startswith('/img/') else src
    im = Image.open(path)
    cut = im.mode in ('RGBA', 'LA') and im.getchannel('A').getextrema()[0] < 250
    if ref.get('crop'):
        im = im.crop(tuple(ref['crop']))
        d = os.path.join(gen.CACHE, 'article'); os.makedirs(d, exist_ok=True)
        h = hashlib.md5(json.dumps(ref, sort_keys=True).encode()).hexdigest()[:10]
        path = os.path.join(d, f'{os.path.basename(src).rsplit(".", 1)[0]}-{h}.{"png" if cut else "jpg"}')
        if not os.path.exists(path):
            (im if cut else im.convert('RGB')).save(path, quality=93)
    return {'path': path, 'ar': im.width / im.height, 'cut': cut, 'size': im.size}


def theme_of(items):
    """Same rule as gen.set_theme (template R). Set elements take the theme of their whole set, so an article pin
    matches the set's own R pins. Otherwise it is measured on this pin's imagery (opaque pixels of cut-outs, all
    pixels of photos): dark = median luminance < 0.4; blue = median dE76 vs the light paper < 30 (white/ivory
    cut-outs only: photos bring their own background, so they are never put on blue silk); else light."""
    import numpy as np
    from PIL import Image
    sets = {it['set'] for it in items if it.get('set')}
    if sets and all(it.get('set') for it in items):
        ts = {gen.set_theme(sid) for sid in sets}
        return ts.pop() if len(ts) == 1 else 'light'
    px = []
    for it in items:
        a = np.asarray(Image.open(it['path']).convert('RGBA').resize((80, 80))).reshape(-1, 4)
        px.append(a[a[:, 3] > 160][:, :3])
    p = np.concatenate(px) / 255.
    lum = 0.2126 * p[:, 0] + 0.7152 * p[:, 1] + 0.0722 * p[:, 2]
    if np.median(lum) < 0.4:
        return 'dark'
    if not all(it['cut'] for it in items):
        return 'light'
    de = np.linalg.norm(gen._lab(p) - gen._lab(np.array([[247, 247, 246]]) / 255.), axis=1)
    return 'blue' if np.median(de) < 30 else 'light'


def img_src(it, theme):
    if it['cut'] and theme in ('dark', 'blue') and 'el' in it:
        return gen.defringed(it['el'])
    return it['path']


def fmt_in(v):
    return f'{v:.0f}' if abs(v - round(v)) < 0.05 else f'{v:.1f}'


# ---------- page ----------
def css(theme):
    bm = os.path.join(gen.FONT_DIR, 'BodoniModa-VariableFont_opsz,wght.ttf')
    c = f'''@font-face{{font-family:B;src:url("file://{bm}")}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:1000px;height:1500px;overflow:hidden}}
body{{position:relative;background:#F7F7F6 url(file://{HERE}/assets/paper-r.jpg) 0 0/1000px 2000px no-repeat;
font-family:B;font-variant-ligatures:none;font-kerning:normal;color:#3C3C3C;--ink:#3C3C3C;--slate:{SLATE};--shadow:rgba(60,50,45,.22)}}
.studio{{text-align:center;font-size:40px;font-weight:450;font-variation-settings:'opsz' 24;color:var(--slate);letter-spacing:.01em;line-height:1}}
.ttl{{text-align:center;font-weight:520;font-variation-settings:'opsz' 60;color:var(--ink);line-height:1.04;letter-spacing:.003em;text-wrap:balance;padding:0 40px}}
.sub{{text-align:center;font-size:34px;font-weight:520;font-variation-settings:'opsz' 28;color:var(--slate);letter-spacing:.06em;text-transform:uppercase;line-height:1.1}}
.abs{{position:absolute}}
.cut{{display:block;width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply}}
.card{{position:absolute;overflow:hidden;border-radius:10px;box-shadow:0 14px 34px var(--shadow),0 2px 6px var(--shadow)}}
.card img{{width:100%;height:100%;object-fit:cover;display:block}}
.lbl{{position:absolute;font-size:30px;font-weight:560;font-variation-settings:'opsz' 28;color:var(--slate);letter-spacing:.05em;text-transform:uppercase;white-space:nowrap;line-height:1}}
'''
    if theme == 'dark':
        c += f'''body{{background:#12060A url(file://{HERE}/assets/wine-wash.jpg) 0 0/1000px 1500px no-repeat;--ink:#F3E9DA;--slate:#C4A56B;--shadow:rgba(0,0,0,.5)}}
.studio{{color:#B39A6A}} .cut{{mix-blend-mode:normal;filter:drop-shadow(0 6px 14px rgba(0,0,0,.55))}}
'''
    if theme == 'blue':
        c += f'''body{{background:#687C8E url(file://{HERE}/assets/blue-silk.jpg) 0 0/1000px 1500px no-repeat;--ink:#FBF9F4;--slate:#F1E3C4;--shadow:rgba(30,42,58,.4)}}
.studio{{color:#EEF2F5}} .cut{{mix-blend-mode:normal;filter:drop-shadow(0 8px 16px rgba(30,42,58,.45))}}
'''
    return c


# shrink each [data-fit] title until it has <= data-lines lines and no word overflows; then lay out [data-flow]
FIT = '''<script>document.fonts.ready.then(()=>{
for(const e of document.querySelectorAll('[data-fit]')){let s=+e.dataset.fit;const L=+e.dataset.lines;
const fits=()=>e.scrollWidth<=e.clientWidth+1&&e.getBoundingClientRect().height<=L*s*1.04*1.02+2;
e.style.fontSize=s+'px';while(!fits()&&s>44){s-=2;e.style.fontSize=s+'px'}}
if(window.layout)window.layout();document.body.dataset.ready=1})</script>'''


def doc(body, theme, extra_css='', script=''):
    return (f'<!doctype html><html data-w=1000 data-h=1500><head><meta charset=utf-8><style>{css(theme)}{extra_css}</style></head>'
            f'<body>{body}{script}{FIT}</body></html>')


def head_block(title, sub, size, lines, top=34):
    return (f'<div class=studio style="position:absolute;top:{top}px;left:0;right:0">JALINA.ART</div>'
            f'<div id=txt class=abs style="left:0;right:0;top:{top + 64}px">'
            f'<div class=ttl data-fit={size} data-lines={lines}>{gen.esc(title)}</div>'
            f'<div class=sub style="margin-top:22px">{gen.esc(sub)}</div></div>')


# ---------- A: hero ----------
def variant_a(spec, theme):
    hero = resolve(spec['hero'])
    items = [hero]
    th = theme if theme != 'auto' else theme_of(items)
    fx = spec.get('hero_fx')
    # text block at the bottom (title max 2 lines), hero centred in the space above it
    body = ('<div class=studio style="position:absolute;top:34px;left:0;right:0">JALINA.ART</div>'
            f'<div id=txt class=abs style="left:0;right:0;bottom:56px"><div class=ttl data-fit=100 data-lines=2>{gen.esc(spec["title"])}</div>'
            f'<div class=sub style="margin-top:22px">{gen.esc(spec["sub"])}</div></div>')
    src = img_src(hero, th)
    extra = ''
    if fx == 'checker-split':  # painted checkerboard behind the LEFT half only: fake vs real transparency
        box = (60, 130, 880, 880)
        w, h = (box[2], box[2] / hero['ar']) if hero['ar'] >= 1 else (box[3] * hero['ar'], box[3])
        x, y = box[0] + (box[2] - w) / 2, box[1] + (box[3] - h) / 2
        pad = 26
        body += (f'<div id=hero class=abs style="left:{x - pad:.0f}px;top:{y - pad:.0f}px;width:{w + 2 * pad:.0f}px;height:{h + 2 * pad:.0f}px">'
                 f'<div class=abs style="inset:0;clip-path:inset(0 50% 0 0);background-color:#fff;'
                 f'background-image:conic-gradient(#D2D2D2 25%,#fff 0 50%,#D2D2D2 0 75%,#fff 0);background-size:56px 56px"></div>'
                 f'<div class=abs style="left:50%;top:-14px;bottom:-14px;border-left:2px dashed var(--slate);opacity:.7"></div>'
                 f'<div class=abs style="inset:{pad}px"><img class=cut style="mix-blend-mode:normal" src="file://{src}"></div>'
                 f'<div class=lbl style="left:0;width:50%;text-align:center;top:calc(100% + 26px)">fake</div>'
                 f'<div class=lbl style="left:50%;width:50%;text-align:center;top:calc(100% + 26px)">real</div></div>')
    elif fx == 'measure':  # dimension line with the real pixel width and print size at 300 DPI
        pw, ph = hero['size']
        box = (130, 230, 740, 700)
        w, h = (box[2], box[2] / hero['ar']) if box[2] / hero['ar'] <= box[3] else (box[3] * hero['ar'], box[3])
        x, y = box[0] + (box[2] - w) / 2, box[1] + (box[3] - h) / 2
        body += (f'<div id=hero class=abs style="left:{x:.0f}px;top:{y - 110:.0f}px;width:{w:.0f}px;height:{h + 230:.0f}px">'
                 f'<div class=lbl style="left:0;right:0;top:0;text-align:center;font-size:34px">{pw} px</div>'
                 f'<svg class=abs style="left:0;top:50px;overflow:visible" width="{w:.0f}" height="24"><g stroke="{SLATE if th == "light" else "#E9DCC0"}" stroke-width="2.5" fill="none">'
                 f'<path d="M0 0V24M{w:.0f} 0V24M2 12H{w - 2:.0f}"/><path d="M14 4 2 12 14 20M{w - 14:.0f} 4 {w - 2:.0f} 12 {w - 14:.0f} 20"/></g></svg>'
                 f'<div class=abs style="left:0;top:110px;width:{w:.0f}px;height:{h:.0f}px"><img class=cut src="file://{src}"></div>'
                 f'<div class=lbl style="left:-200px;right:-200px;top:{h + 150:.0f}px;text-align:center;font-size:34px">= {fmt_in(pw / 300)} × {fmt_in(ph / 300)} in at 300 DPI</div></div>')
    else:
        box = (60, 120, 880, 900)
        w, h = (box[2], box[2] / hero['ar']) if box[2] / hero['ar'] <= box[3] else (box[3] * hero['ar'], box[3])
        x, y = box[0] + (box[2] - w) / 2, box[1] + (box[3] - h) / 2
        inner = f'<img class=cut src="file://{src}">' if hero['cut'] else f'<img src="file://{src}">'
        body += f'<div id=hero class="{"abs" if hero["cut"] else "card"}" style="left:{x:.0f}px;top:{y:.0f}px;width:{w:.0f}px;height:{h:.0f}px">{inner}</div>'
    # centre the hero vertically between the studio line and the text block
    script = ('<script>window.layout=()=>{const h=document.getElementById("hero"),t=document.getElementById("txt");'
              'const top=100,bot=t.getBoundingClientRect().top-30,r=h.getBoundingClientRect();'
              'const lab=[...h.querySelectorAll(".lbl")].reduce((m,e)=>Math.max(m,e.getBoundingClientRect().bottom),r.bottom);'
              'const H=lab-r.top;let y=top+(bot-top-H)/2;h.style.top=Math.round(y)+"px"}</script>')
    return doc(body, th, extra, script), th


# ---------- B: stack ----------
SLOTS = {  # (centre x, centre y, box w, box h, rotation) inside the area below the title block
    3: [(350, 0.22, 600, 470, -4), (650, 0.55, 600, 470, 3.5), (370, 0.85, 620, 430, -2)],
    2: [(390, 0.28, 720, 560, -3), (610, 0.74, 720, 560, 3)],
}


def variant_b(spec, theme):
    items = [resolve(r) for r in spec['stack']]
    th = theme if theme != 'auto' else theme_of(items)
    body = head_block(spec['title'], spec.get('b_sub') or spec['sub'], 100, 2)
    area_top = 420
    if spec.get('stack_fx') == 'scale':  # one cut-out at its real print width for 300 / 240 / 150 DPI
        it = items[0]
        pw, ph = it['size']
        dpis = [300, 240, 150]
        inch = [pw / d for d in dpis]
        k = 760 / max(inch)  # the 150 DPI copy is 760 px wide, the others to the same scale
        src = img_src(it, th)
        ws = [wi * k for wi in inch]
        hs = [w / it['ar'] for w in ws]
        # cascade: small top-left, medium right, big bottom-left (front); labels in the free corners
        boxes = [(60, area_top + 20), (1000 - ws[1] - 50, area_top + 180), (40, 1460 - hs[2])]
        labels = [f'left:{60 + ws[0] + 30:.0f}px;top:{area_top + 50:.0f}px',
                  f'left:60px;top:{area_top + 20 + hs[0] + 40:.0f}px',
                  f'right:40px;top:{1460 - hs[2] * 0.45:.0f}px;text-align:right']
        for i, (d, wi) in enumerate(zip(dpis, inch)):
            x, y = boxes[i]
            body += (f'<div class=abs style="left:{x:.0f}px;top:{y:.0f}px;width:{ws[i]:.0f}px;height:{hs[i]:.0f}px;z-index:{i}">'
                     f'<img class=cut style="mix-blend-mode:normal;filter:drop-shadow(0 8px 14px var(--shadow))" src="file://{src}"></div>'
                     f'<div class=lbl style="{labels[i]};z-index:5;line-height:1.25">{d} DPI<br>{fmt_in(wi)} in wide</div>')
        return doc(body, th), th
    slots = SLOTS[min(3, max(2, len(items)))]
    ah = 1500 - area_top - 20
    for z, (it, (cx, fy, bw, bh, rot)) in enumerate(zip(items, slots)):
        w, h = (bw, bw / it['ar']) if bw / it['ar'] <= bh else (bh * it['ar'], bh)
        cy = area_top + fy * ah
        cy = min(cy, 1500 - 40 - h / 2)
        src = img_src(it, th)
        if it['cut']:
            body += (f'<div class=abs style="left:{cx - w / 2:.0f}px;top:{cy - h / 2:.0f}px;width:{w:.0f}px;height:{h:.0f}px;'
                     f'transform:rotate({rot}deg);z-index:{z}"><img class=cut src="file://{src}"></div>')
        else:
            body += (f'<div class=card style="left:{cx - w / 2:.0f}px;top:{cy - h / 2:.0f}px;width:{w:.0f}px;height:{h:.0f}px;'
                     f'transform:rotate({rot}deg);z-index:{z}"><img src="file://{src}"></div>')
    return doc(body, th), th


# ---------- C: title-led ----------
def variant_c(spec, theme):
    it = resolve(spec['cutout'])
    th = theme if theme != 'auto' else theme_of([it])
    body = head_block(spec.get('c_title') or spec['title'], spec.get('c_sub') or spec['sub'], 124, 3, top=40)
    src = img_src(it, th)
    body += f'<div id=hero class=abs><img class=cut src="file://{src}"></div>'
    script = ('<script>window.layout=()=>{const t=document.getElementById("txt").getBoundingClientRect();'
              f'const ar={it["ar"]:.4f};const top=t.bottom+50,bot=1500-50,bw=900;let bh=bot-top;'
              'let w=Math.min(bw,bh*ar),h=w/ar;const h0=document.getElementById("hero");'
              'h0.style.left=Math.round((1000-w)/2)+"px";h0.style.top=Math.round(top+(bh-h)/2)+"px";'
              'h0.style.width=Math.round(w)+"px";h0.style.height=Math.round(h)+"px"}</script>')
    return doc(body, th, '', script), th


VARIANTS = {'A': variant_a, 'B': variant_b, 'C': variant_c}


def render(prefix):
    subprocess.run(['node', os.path.join(HERE, 'shot.js'), prefix], check=True, cwd=HERE)
    os.makedirs(OUT, exist_ok=True)
    for f in glob.glob(os.path.join(HERE, 'out', prefix + '*.jpg')):
        shutil.copy(f, os.path.join(OUT, os.path.basename(f)))
        print('->', os.path.relpath(os.path.join(OUT, os.path.basename(f)), HERE))


def sheet(prefix='art-'):
    """All rendered variants side by side (rows = posts, columns = A/B/C), labelled; plus a 236px-wide
    feed-size version of every pin (_sheet-236.jpg) to check that the titles stay readable."""
    from PIL import Image, ImageDraw, ImageFont
    files = sorted(glob.glob(os.path.join(OUT, prefix + '*-[ABC].jpg')))
    rows = {}
    for f in files:
        m = re.match(r'(.*)-([ABC])\.jpg$', os.path.basename(f))
        rows.setdefault(m.group(1), {})[m.group(2)] = f
    font = ImageFont.truetype(os.path.join(gen.FONT_DIR, 'BodoniModa-VariableFont_opsz,wght.ttf'), 26)
    for tw, name, lab in ((400, '_sheet.jpg', True), (236, '_sheet-236.jpg', False)):
        th = int(tw * 1.5)
        pad, head = 20, 50 if lab else 26
        S = Image.new('RGB', (pad + 3 * (tw + pad), len(rows) * (th + head + pad) + pad), '#E9E6E1')
        d = ImageDraw.Draw(S)
        for r, (slug, v) in enumerate(sorted(rows.items())):
            y = pad + r * (th + head + pad)
            for c, k in enumerate('ABC'):
                x = pad + c * (tw + pad)
                d.text((x, y + 4), f'{k} · {slug[4:]}'[:30] if lab else k, fill='#3C3C3C', font=font if lab else None)
                if k in v:
                    S.paste(Image.open(v[k]).convert('RGB').resize((tw, th), Image.LANCZOS), (x, y + head))
        S.save(os.path.join(OUT, name), quality=88)
        print('->', os.path.join('article-samples', name))


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('target', help='preset name (see `presets`), path to a spec .json, `presets` or `sheet`')
    ap.add_argument('variants', nargs='*', default=[])
    ap.add_argument('--theme', choices=['auto', 'light', 'dark', 'blue'], default='auto')
    ap.add_argument('--render', action='store_true', help='render to article-samples/ with node shot.js')
    a = ap.parse_args()
    if a.target == 'presets':
        for k, v in PRESETS.items():
            print(f'{k:32s} {v["title"]} | {v["sub"]}')
        return
    if a.target == 'sheet':
        sheet(*(a.variants[:1] or []))
        return
    if a.target.endswith('.json'):
        spec = json.load(open(a.target)); slug = spec['slug']
    else:
        spec = PRESETS.get(a.target) or sys.exit(f'unknown preset {a.target}; see `article.py presets`')
        slug = a.target
    safe = slug.strip('/').replace('/', '-')
    for v in (a.variants or ['A', 'B', 'C']):
        html_, th = VARIANTS[v.upper()](spec, a.theme)
        name = f'art-{safe}-{v.upper()}'
        open(os.path.join(HERE, name + '.html'), 'w').write(html_)
        print(name + '.html', '| theme', th)
    if a.render:
        render(f'art-{safe}-')


if __name__ == '__main__':
    main()
