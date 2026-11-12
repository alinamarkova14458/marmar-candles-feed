"""Pinterest pin templates v2 for jalina.art (1000x1500), based on the Nov 2026 top-pin research:
product itself, light background, little/no text, no price, number only inside a short headline,
brand = "jalina.art" plaque (pill, DM Serif 42px) bottom centre, readable at 236 px feed width.

  python3 gen.py A <set-id> [-n 12]             set sheet: grid of the set's own elements
  python3 gen.py B <set-id> [index] [--text T]  one hero element (1-based index, see `gen.py list <set-id>`)
  python3 gen.py B post:<slug> [index]          one hero = n-th product image of a blog post (white bg)
  python3 gen.py C <post-slug> [--title T]      numbered list pin from a blog post
  python3 gen.py D <set-id> [mockup 1-4] [--title T]   "in use": one real mockup as hero + strip of elements
  python3 gen.py S <set-id> [--n 16..30] [--title T] [--size 1000x1500|1000x2000] [--script W] [--seed K]
                                                product sheet: 16-25 set elements on cotton paper (Alina's favourite look)
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


# ---------- S: product sheet (Nov 12 2026, after Alina's favourite top pins) ----------
# The whole pin IS the product: 16-25 set elements packed edge to edge on cotton watercolor paper,
# thin spaced-caps serif title, tiny "WATERCOLOR CLIPART · N PNG · 300 DPI" line, small jalina.art.
PAPER = os.path.join(HERE, 'assets', 'cotton-paper.jpg')  # made by paper.py (procedural, 1000x2000)


def ink(path):
    """Fraction of the trimmed bbox covered by paint (alpha) -> sparse items get a bigger slot."""
    from PIL import Image
    import numpy as np
    a = np.asarray(Image.open(path).getchannel('A').resize((64, 64)))
    return max(0.25, min(0.95, float((a > 40).mean())))


def sheet_layout(items, bw, bh, gap, seed=1, tries=1500):
    """items: [(aspect, rel_height, fill, is_hero)]. Random orders x greedy rows, each row justified
    to bw (stretch capped), global scale binary-searched to fill bh; best-scoring order wins.
    Returns positions [(x,y,w,h)] in item order (box coordinates)."""
    import random
    rnd = random.Random(seed)
    n = len(items)
    best = None

    def rows_at(order, k):
        rows, cur, cw = [], [], 0
        for i in order:
            w = items[i][0] * items[i][1] * k
            if cur and cw + gap + w > bw:
                rows.append(cur); cur, cw = [], 0
            cur.append(i); cw += (gap if len(cur) > 1 else 0) + w
        rows.append(cur)
        out, th = [], 0
        for row in rows:
            nat = sum(items[i][0] * items[i][1] * k for i in row)
            sc = min(1.18, (bw - gap * (len(row) - 1)) / nat)
            rh = max(items[i][1] * k * sc for i in row)
            out.append((row, sc, rh)); th += rh
        return out, th + gap * (len(rows) - 1)

    for _ in range(tries):
        order = list(range(n)); rnd.shuffle(order)
        # rows chosen at width-scale k; if they are too tall, the whole sheet is scaled down to fit
        for k in (bw * f / 6 for f in (0.8, 0.88, 0.96, 1.04, 1.12, 1.2, 1.3, 1.4, 1.5, 1.65)):
            rows, th = rows_at(order, k)
            if len(rows) < 3 or any(len(r) > 6 for r, _, _ in rows):
                continue
            fit = min(1.0, bh / th)
            sz = lambda i, sc: items[i][1] * k * sc * fit
            area = sum(items[i][0] * sz(i, sc) ** 2 * items[i][2] for row, sc, _ in rows for i in row)
            empty = sum(bw - sum(items[i][0] * sz(i, sc) for i in row) for row, sc, _ in rows)
            waste = sum(items[i][0] * sz(i, sc) * (rh * fit - sz(i, sc)) for row, sc, rh in rows for i in row)
            heroes = [sum(1 for i in row if items[i][3]) for row, _, _ in rows]
            cnt = [len(r) for r, _, _ in rows]
            uneven = sum(abs(c - sum(cnt) / len(cnt)) for c in cnt) / len(cnt)
            score = (area / (bw * bh) - 0.03 * uneven - 0.04 * empty / bw - 0.7 * waste / (bw * bh) - 0.15 * (1 - th * fit / bh)
                     - 0.08 * sum(max(0, h - 1) for h in heroes) - 0.1 * sum(1 for r, _, _ in rows if len(r) == 1))
            if best is None or score > best[0]:
                best = (score, rows, th, k, fit)
    _, rows, th, k, fit = best
    nr = len(rows)
    th *= fit
    vgap = gap * fit
    vgap = min(gap * 2.4, vgap + (bh - th) / max(1, nr - 1))
    used = th + (vgap - gap * fit) * (nr - 1)
    y = (bh - used) / 2
    pos = [None] * n
    for row, sc, rh in rows:
        rh *= fit
        ws = [items[i][0] * items[i][1] * k * sc * fit for i in row]
        free = bw - sum(ws)
        if len(row) == 1:
            g, x = 0, free / 2
        elif free / (len(row) - 1) > gap * 2.2:  # narrow row: spread with outer margins
            g = free / (len(row) + 1); x = g
        else:
            g, x = free / (len(row) - 1), 0
        for i, w in zip(row, ws):
            h = items[i][1] * k * sc * fit
            pos[i] = (x, y + (rh - h) / 2, w, h)
            x += w + g
        y += rh + vgap
    return pos


def sheet_style(s):
    d = (s.get('desc', '') + ' ' + s['name']).lower()
    return 'crayon clipart' if 'crayon' in d else 'watercolor clipart'


SHEET_FIT = ('<script>document.fonts.ready.then(()=>{const e=document.querySelector(".ttl");let s=+e.dataset.fit;'
             'e.style.fontSize=s+"px";while(e.scrollWidth>e.clientWidth&&s>34){s-=2;e.style.fontSize=s+"px"}'
             'document.body.dataset.ready=1})</script>')


def tmpl_s(sid, n, title=None, size=(1000, 1500), script=None, seed=1):
    w_, h_ = size
    s = SHOP[sid]
    els = [e for e in set_elements(sid) if not SKIP.search(e[0])]
    flat = [e for e in els if 0.45 <= e[3] <= 2.2]  # no very wide garlands unless needed
    if n is None:
        n = min(25, len(flat)) if len(flat) >= 16 else min(25, len(els))
    if len(flat) >= n:
        els = flat
    if len(els) > n:  # spread the pick over the alphabetical list -> variety
        step = len(els) / n
        els = [els[int(i * step)] for i in range(n)]
    fills = [ink(e[1]) for e in els]
    # visual weight: densest 3 (cakes, wreaths, houses) = heroes, sparsest quarter = small accents
    rank = sorted(range(len(els)), key=lambda i: -fills[i])
    hero = set(rank[:3]); small = set(rank[-max(1, len(els) // 4):])
    items = []
    for i, e in enumerate(els):
        T = 1.8 if i in hero else 0.6 if i in small else 1.0
        items.append((e[3], math.sqrt(T / (e[3] * fills[i])), fills[i], i in hero))
    title = (title or s['name']).upper()
    sub = f'{sheet_style(s)} · {s["count"]} PNG · 300 DPI'.upper()
    ttop = 128 if script else 70
    top = ttop + 150
    side, bottom = 46, 100
    bw, bh = w_ - 2 * side, h_ - top - bottom
    pos = sheet_layout(items, bw, bh, 24, seed)
    body = f'<div class=scr>{esc(script)}</div>' if script else ''
    body += f'<div class=ttl data-fit=62>{esc(title)}</div><div class=sub>{esc(sub)}</div>'
    for e, (x, y, w, h) in zip(els, pos):
        p = 0.03 * min(w, h)
        body += (f'<div class=abs style="left:{side+x+p:.0f}px;top:{top+y+p:.0f}px;width:{w-2*p:.0f}px;'
                 f'height:{h-2*p:.0f}px"><img src="file://{e[2]}"></div>')
    css = f'''@font-face{{font-family:S;src:url(file://{FONTS}/DMSerifDisplay-400-latin.woff2)}}
@font-face{{font-family:C;src:url(file://{FONTS}/Caveat-700-latin.woff2)}}
@font-face{{font-family:M;src:url(file://{FONTS}/Manrope-400-latin.woff2)}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:{w_}px;height:{h_}px;overflow:hidden}}
body{{position:relative;background:#FAF7F1 url(file://{PAPER}) 0 0/{w_}px {h_}px no-repeat;color:#3A2C28}}
.abs{{position:absolute}} .abs img{{width:100%;height:100%;object-fit:contain;display:block;mix-blend-mode:multiply}}
.scr{{position:absolute;top:56px;left:0;right:0;text-align:center;font-family:C;font-size:50px;color:#A0525E;line-height:1}}
.ttl{{position:absolute;top:{ttop}px;left:50px;right:50px;text-align:center;font-family:S;line-height:1.05;
letter-spacing:.2em;padding-left:.2em;white-space:nowrap;color:#3B2D29}}
.sub{{position:absolute;top:{ttop+96}px;left:0;right:0;text-align:center;font-family:M;font-size:19px;
letter-spacing:.32em;padding-left:.32em;color:#7D6A64}}
.brand{{position:absolute;left:0;right:0;bottom:34px;text-align:center;font-family:S;font-size:32px;
letter-spacing:.06em;color:#6B5550}}'''
    doc = (f'<!doctype html><html data-w={w_} data-h={h_}><head><meta charset=utf-8><style>{css}</style></head>'
           f'<body>{body}<div class=brand>jalina.art</div>{SHEET_FIT}</body></html>')
    return doc, [e[0] for e in els]


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
    ap.add_argument('template', choices=['A', 'B', 'C', 'D', 'S', 'list'])
    ap.add_argument('target')
    ap.add_argument('index', nargs='?', type=int)
    ap.add_argument('-n', '--n', type=int, default=None, help='A: number of elements (9-20, default 12); S: 16-30 (default: all non-lettering, non-garland elements, max 25)')
    ap.add_argument('--size', default='1000x1500', help='S: 1000x1500 or 1000x2000')
    ap.add_argument('--script', help='S: optional small script word above the title')
    ap.add_argument('--seed', type=int, default=1, help='S: layout seed (try another if a sheet looks off)')
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
    if a.template == 'S':
        sw, sh = map(int, a.size.lower().split('x'))
        doc, used = tmpl_s(a.target, a.n and max(16, min(30, a.n)), a.title, (sw, sh), a.script, a.seed)
        name = f'v2-S-{a.target}' + ('-tall' if sh > 1600 else '')
    elif a.template == 'A':
        doc, used = tmpl_a(a.target, max(9, min(20, a.n or 12)), a.title)
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
