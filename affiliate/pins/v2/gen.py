"""Pinterest pin templates v2 for jalina.art (1000x1500), based on the Nov 2026 top-pin research:
product itself, light background, little/no text, no price, number only inside a short headline,
brand = "jalina.art" plaque (pill, DM Serif 42px) bottom centre, readable at 236 px feed width.

  python3 gen.py A <set-id> [-n 12]             set sheet: grid of the set's own elements
  python3 gen.py B <set-id> [index] [--text T]  one hero element (1-based index, see `gen.py list <set-id>`)
  python3 gen.py B post:<slug> [index]          one hero = n-th product image of a blog post (white bg)
  python3 gen.py C <post-slug> [--title T]      numbered list pin from a blog post
  python3 gen.py D <set-id> [mockup 1-4] [--title T]   "in use": one real mockup as hero + strip of elements
  python3 gen.py list <set-id>                  show element indices of a set

Writes v2-<T>-<id>.html here; render with `node shot.js` -> out/*.jpg.
Set ZIPs are unpacked once (trimmed, downscaled) into $PINS_CACHE (default: <tmp>/jalina-pins-v2).
"""
import argparse, io, json, math, os, re, sys, tempfile, zipfile, glob, html

HERE = os.path.dirname(os.path.abspath(__file__))
FACTORY = '/home/user/jalina-factory/ready'
BLOG = '/home/user/jalina-blog'
FONTS = f'{BLOG}/public/fonts'
CACHE = os.environ.get('PINS_CACHE') or os.path.join(tempfile.gettempdir(), 'jalina-pins-v2')
SHOP = {s['id']: s for s in json.load(open(f'{BLOG}/src/shop.json'))}
W, H = 1000, 1500
# lettering-only / text PNGs are skipped where possible
SKIP = re.compile(r'lettering|word-?art|quote|text|alphabet|monogram|saying|-sign\b|blank-banner', re.I)

CSS = f'''@font-face{{font-family:S;src:url(file://{FONTS}/DMSerifDisplay-400-latin.woff2)}}
@font-face{{font-family:S;src:url(file://{FONTS}/DMSerifDisplay-400-latin-ext.woff2);unicode-range:U+0100-024F}}
@font-face{{font-family:C;src:url(file://{FONTS}/Caveat-700-latin.woff2)}}
@font-face{{font-family:M;src:url(file://{FONTS}/Manrope-400-latin.woff2)}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:{W}px;height:{H}px;overflow:hidden}}
body{{position:relative;font-family:M;color:#2B1418}}
.brand{{position:absolute;left:50%;bottom:34px;transform:translateX(-50%);padding:6px 30px 9px;border-radius:999px;
background:rgba(255,255,255,.82);border:2px solid rgba(158,27,50,.16);box-shadow:0 3px 10px rgba(43,20,24,.07);
font-family:S;font-size:42px;line-height:1.1;letter-spacing:.5px;color:#6E2A36;white-space:nowrap}}
.hl{{position:absolute;left:60px;right:60px;text-align:center;font-family:S;font-weight:400;line-height:1.02;color:#2B1418;text-wrap:balance}}
.hl .n{{color:#9E1B32}}
.abs{{position:absolute}}
.abs img{{width:100%;height:100%;object-fit:contain;display:block}}
'''
# shrink the headline until it fits its box (max 2 lines)
FIT = '''<script>document.fonts.ready.then(()=>{for(const e of document.querySelectorAll('[data-fit]')){
let s=+e.dataset.fit;const mh=+e.dataset.mh;e.style.fontSize=s+'px';
while((e.scrollHeight>mh||e.scrollWidth>e.clientWidth)&&s>30){s-=2;e.style.fontSize=s+'px'}}
document.body.dataset.ready=1})</script>'''


def page(body, bg, extra=''):
    return f'<!doctype html><html><head><meta charset=utf-8><style>{CSS}body{{background:{bg}}}{extra}</style></head><body>{body}<div class=brand>jalina.art</div>{FIT}</body></html>'


def esc(s):
    return html.escape(s, quote=True)


# ---------- set elements ----------
def set_elements(sid):
    """Return [(name, path500, path1000, aspect)] sorted by file name; unzip+trim+downscale once."""
    from PIL import Image
    d = os.path.join(CACHE, sid)
    meta = os.path.join(d, 'meta.json')
    if os.path.exists(meta):
        return json.load(open(meta))
    zips = glob.glob(f'{FACTORY}/{sid}/*.zip')
    if not zips:
        sys.exit(f'no ZIP in {FACTORY}/{sid}')
    os.makedirs(f'{d}/500', exist_ok=True); os.makedirs(f'{d}/1000', exist_ok=True)
    out = []
    with zipfile.ZipFile(zips[0]) as z:
        for n in sorted(x for x in z.namelist() if x.lower().endswith('.png')):
            base = os.path.basename(n)
            im = Image.open(io.BytesIO(z.read(n))).convert('RGBA')
            bb = im.getchannel('A').point(lambda a: 255 if a > 8 else 0).getbbox()
            if bb:
                im = im.crop(bb)
            for size in (1000, 500):
                t = im.copy(); t.thumbnail((size, size), Image.LANCZOS)
                t.save(f'{d}/{size}/{base}', optimize=True)
            out.append([base, f'{d}/500/{base}', f'{d}/1000/{base}', im.width / im.height])
            print('  cached', base, file=sys.stderr)
    json.dump(out, open(meta, 'w'))
    return out


def pretty(fname):
    return re.sub(r'^watercolor-', '', fname[:-4]).replace('-', ' ')


def pick_for_sheet(els, n):
    good = [e for e in els if not SKIP.search(e[0]) and 0.4 <= e[3] <= 2.6]
    rest = [e for e in els if e not in good and not SKIP.search(e[0])]
    pool = good + rest
    if len(pool) < n:
        pool += [e for e in els if e not in pool]
    if len(good) > n:  # spread evenly over the alphabetical list -> variety
        step = len(good) / n
        pool = [good[int(i * step)] for i in range(n)]
    return pool[:n]


def justified(items, box_w, box_h, gap):
    """Split items (aspect ratios, in order) into rows that fill box_w; choose row count so the
    total height is closest to box_h, then scale down uniformly if needed. Returns [(x,y,w,h)]."""
    best = None
    n = len(items)
    for k in range(2, n + 1):
        # balanced consecutive partition into k rows (DP on squared deviation of aspect sums)
        tot = sum(items); target = tot / k
        INF = float('inf')
        dp = [[INF] * (k + 1) for _ in range(n + 1)]; cut = [[0] * (k + 1) for _ in range(n + 1)]
        dp[0][0] = 0
        for i in range(1, n + 1):
            for r in range(1, min(k, i) + 1):
                for j in range(r - 1, i):
                    c = dp[j][r - 1] + (sum(items[j:i]) - target) ** 2
                    if c < dp[i][r]:
                        dp[i][r] = c; cut[i][r] = j
        rows, i = [], n
        for r in range(k, 0, -1):
            j = cut[i][r]; rows.append((j, i)); i = j
        rows.reverse()
        if any(b - a > 4 for a, b in rows) or any(b - a < 1 for a, b in rows):
            continue
        hs = [(box_w - gap * (b - a - 1)) / sum(items[a:b]) for a, b in rows]
        th = sum(hs) + gap * (k - 1)
        score = abs(th - box_h) + (0 if max(hs) < box_h * 0.42 else 1e4)
        if best is None or score < best[0]:
            best = (score, rows, hs, th)
    _, rows, hs, th = best
    s = min(1.0, box_h / th)
    out, y = [], (box_h - th * s) / 2
    for (a, b), h in zip(rows, hs):
        h *= s
        rw = sum(items[a:b]) * h + gap * (b - a - 1)
        x = (box_w - rw) / 2
        for i in range(a, b):
            w = items[i] * h
            out.append((x, y, w, h)); x += w + gap
        y += h + gap
    return out


def headline_html(text, top, size, mh):
    m = re.match(r'^(\d+\+?)\s+(.*)$', text)
    inner = f'<span class=n>{esc(m.group(1))}</span> {esc(m.group(2))}' if m else esc(text)
    return f'<div class=hl data-fit={size} data-mh={mh} style="top:{top}px;max-height:{mh + 20}px">{inner}</div>'


def tmpl_a(sid, n, title=None):
    s = SHOP[sid]
    els = pick_for_sheet(set_elements(sid), n)
    title = title or f'{s["count"]} {s["name"]} PNG'
    box = (44, 240, W - 88, H - 240 - 135)  # x, y, w, h
    pos = justified([e[3] for e in els], box[2], box[3], 26)
    body = headline_html(title, 70, 84, 170)
    for e, (x, y, w, h) in zip(els, pos):
        p = 0.04 * min(w, h)  # small breathing room inside each slot
        body += f'<div class=abs style="left:{box[0]+x+p:.0f}px;top:{box[1]+y+p:.0f}px;width:{w-2*p:.0f}px;height:{h-2*p:.0f}px"><img src="file://{e[1]}"></div>'
    return page(body, '#FBF6EE'), [e[0] for e in els]


CHECKER = 'background-color:#FFFFFF;background-image:conic-gradient(#F1EEEC 25%,#FFFFFF 0 50%,#F1EEEC 0 75%,#FFFFFF 0);background-size:48px 48px'


def tmpl_b(target, idx, text=None):
    if target.startswith('post:'):
        items = post_items(target[5:])
        it = items[(idx or 1) - 1]
        img, label, bg, ar = it['img'], it['title'], '#FFFFFF', 1.5
        text = text if text is not None else ''
    else:
        s = SHOP[target]
        els = set_elements(target)
        if idx:
            e = els[idx - 1]
        else:
            cand = [e for e in els if not SKIP.search(e[0]) and 0.6 <= e[3] <= 1.4] or els
            e = cand[0]
        img, label, bg, ar = e[2], pretty(e[0]), f'#FFFFFF;{CHECKER}', e[3]
        if text is None:
            words = s['name'].split()
            text = s['name'] if len(words) <= 3 else ' '.join(words[-3:])
    top = 230 if text else 110
    bw, bh = W - 120, H - top - 150
    w, h = (bw, bw / ar) if bw / ar <= bh else (bh * ar, bh)
    x, y = (W - w) / 2, top + (bh - h) / 2
    body = (headline_html(text, 80, 78, 110) if text else '')
    body += f'<div class=abs style="left:{x:.0f}px;top:{y:.0f}px;width:{w:.0f}px;height:{h:.0f}px"><img src="file://{img}"></div>'
    return page(body, bg), [label]


# mockup-N.jpg in jalina-factory/ready/<set>/ (1200x800): 1 sticker sheet, 2 toppers + tags, 3 tote, 4 tee + bodysuit


def tmpl_d(sid, m, title=None, kicker=None, strip=3):
    from PIL import Image
    s = SHOP[sid]
    mp = f'{FACTORY}/{sid}/mockup-{m}.jpg'
    if not os.path.exists(mp):
        sys.exit(f'no {mp}')
    mw, mh = Image.open(mp).size
    title = title or f'{s["count"]} {s["name"]} PNG'
    kicker = kicker or ''  # mockups already carry their own use label; kicker only on request
    body = ''
    top = 64
    if kicker:
        body += f'<div class=kick style="top:{top}px">{esc(kicker)}</div>'
        top += 66
    body += headline_html(title, top, 80, 170)
    # hero mockup: whole image, never cropped, rounded card; stack hero + strip centred
    # between the headline (2 lines max) and the brand plaque
    hw = W - 2 * 24
    hh = hw * mh / mw
    y0, y1 = top + 180, H - 120
    els = pick_for_sheet(set_elements(sid), strip) if strip > 0 else []
    ars = [e[3] for e in els]
    gap = 40
    sh = 0 if not els else min(320, (W - 100 - gap * (len(els) - 1)) / sum(ars), max(0, y1 - y0 - hh - 90))
    g = (y1 - y0 - hh - sh) / (3 if sh >= 140 else 2)
    hy = y0 + g
    body += f'<div class="abs hero" style="left:24px;top:{hy:.0f}px;width:{hw:.0f}px;height:{hh:.0f}px"><img src="file://{mp}"></div>'
    used = [os.path.basename(mp)]
    if sh >= 140:  # strip of 3-4 set elements under the mockup
        x = (W - (sum(ars) * sh + gap * (len(els) - 1))) / 2
        y = hy + hh + g
        for e in els:
            w = e[3] * sh
            body += f'<div class=abs style="left:{x:.0f}px;top:{y:.0f}px;width:{w:.0f}px;height:{sh:.0f}px"><img src="file://{e[1]}"></div>'
            x += w + gap
        used += [e[0] for e in els]
    extra = ('.hero{border-radius:22px;overflow:hidden;box-shadow:0 10px 30px rgba(43,20,24,.13)}'
             '.kick{position:absolute;left:0;right:0;text-align:center;font-family:C;font-size:54px;color:#9E1B32;line-height:1}')
    return page(body, '#FBF6EE', extra), used


# ---------- blog posts ----------
def post_items(slug):
    md = open(f'{BLOG}/src/content/posts/{slug}.md').read()
    fm = re.search(r'^title:\s*"?(.*?)"?\s*$', md, re.M).group(1)
    items, cur = [], None
    for line in md.splitlines():
        m = re.match(r'^##\s+(\d+)\.\s+(.*)$', line)
        if m:
            cur = {'n': int(m.group(1)), 'title': m.group(2).strip(), 'img': None}; items.append(cur); continue
        if line.startswith('## '):
            cur = None; continue
        m = re.search(r'!\[[^\]]*\]\((/img/[^)\s]+)\)', line)
        if cur and not cur['img'] and m:
            cur['img'] = BLOG + '/public' + m.group(1)
    items = [i for i in sorted(items, key=lambda i: i['n']) if i['img']]
    for i in items:
        i['post_title'] = fm
    if not items:
        sys.exit(f'no numbered "## N. Title" items with images in {slug}.md')
    return items


def default_list_title(items):
    t = items[0]['post_title'].split(':')[0].strip()
    t = re.sub(r'^(The\s+)?(\d+\s+)?(Best\s+)?', '', t)
    t = re.sub(r'\s+PNG$', '', t)  # "Tumbler Wrap PNG" -> "Tumbler Wraps"
    last = t.split()[-1]
    if last in ('Clipart', 'Art'):
        t += ' Sets'
    elif last in ('SVG', 'PNG'):
        t += ' Files'
    elif not last.endswith('s'):
        t += 's'
    return f'{len(items)} {t}'


def tmpl_c(slug, title=None, bg='#FCEFEA'):
    items = post_items(slug)
    title = title or default_list_title(items)
    shown = items[:8] if len(items) >= 8 else items[:6] if len(items) >= 6 else items
    cols = 1 if len(shown) <= 2 else 2
    rows = math.ceil(len(shown) / cols)
    top, bottom, gap, side = 250, 135, 18, 50
    th = (H - top - bottom - gap * (rows - 1)) / rows
    tw = min((W - 2 * side - gap * (cols - 1)) / cols, th * 1.5)
    th = tw / 1.5
    gh = rows * th + gap * (rows - 1)
    y0 = top + (H - top - bottom - gh) / 2
    body = headline_html(title, 70, 84, 170)
    for k, it in enumerate(shown):
        r, c = divmod(k, cols)
        in_row = min(cols, len(shown) - r * cols)
        x0 = (W - (in_row * tw + gap * (in_row - 1))) / 2
        x, y = x0 + c * (tw + gap), y0 + r * (th + gap)
        body += f'<div class="abs tile" style="left:{x:.0f}px;top:{y:.0f}px;width:{tw:.0f}px;height:{th:.0f}px"><img src="file://{it["img"]}"></div>'
    extra = '.tile{border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 6px 18px rgba(43,20,24,.10)}'
    return page(body, bg, extra), [f'{i["n"]}. {i["title"]}' for i in shown]


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('template', choices=['A', 'B', 'C', 'D', 'list'])
    ap.add_argument('target')
    ap.add_argument('index', nargs='?', type=int)
    ap.add_argument('-n', type=int, default=12, help='A: number of elements (9-20)')
    ap.add_argument('--title', help='A/C/D: override headline (keep it true: number = real count)')
    ap.add_argument('--text', help='B: 0-3 words over the hero ("" = no text)')
    ap.add_argument('--strip', type=int, default=3, help='D: set elements under the mockup (0 = none, 3-4)')
    ap.add_argument('--kicker', help='D: small Caveat line above the headline ("" = none)')
    ap.add_argument('--bg', default='#FCEFEA', help='C: light/pastel background')
    a = ap.parse_args()
    if a.template == 'list':
        for i, e in enumerate(set_elements(a.target), 1):
            print(f'{i:2d}  {e[0]}  (aspect {e[3]:.2f}){"  [skip: lettering]" if SKIP.search(e[0]) else ""}')
        return
    if a.template == 'A':
        doc, used = tmpl_a(a.target, max(9, min(20, a.n)), a.title)
        name = f'v2-A-{a.target}'
    elif a.template == 'B':
        doc, used = tmpl_b(a.target, a.index, a.text)
        name = f'v2-B-{a.target.replace("post:", "post-")}' + (f'-{a.index}' if a.index else '')
    elif a.template == 'D':
        m = a.index or 1
        doc, used = tmpl_d(a.target, m, a.title, a.kicker, a.strip)
        name = f'v2-D-{a.target}-m{m}'
    else:
        doc, used = tmpl_c(a.target, a.title, a.bg)
        name = f'v2-C-{a.target}'
    open(os.path.join(HERE, name + '.html'), 'w').write(doc)
    print(name + '.html', '|', '; '.join(used))


if __name__ == '__main__':
    main()
