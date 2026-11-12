"""Pinterest pin templates v2 for jalina.art (1000x1500), based on the Nov 2026 top-pin research:
product itself, light background, little/no text, no price, number only inside a short headline,
brand = "jalina.art" plaque (pill, DM Serif 42px) bottom centre, readable at 236 px feed width.

  python3 gen.py A <set-id> [-n 12]             set sheet: grid of the set's own elements
  python3 gen.py B <set-id> [index] [--text T]  one hero element (1-based index, see `gen.py list <set-id>`)
  python3 gen.py B post:<slug> [index]          one hero = n-th product image of a blog post (white bg)
  python3 gen.py C <post-slug> [--title T]      numbered list pin from a blog post
  python3 gen.py D <set-id> [mockup 1-4] [--title T]   "in use": one real mockup as hero + strip of elements
  python3 gen.py S <set-id> [--n 6..12] [--title T] [--size 1000x1500|1000x2000] [--paper 1|2|3]
                                                product sheet: 9 (tall 12) big set elements on cotton paper
  python3 gen.py R <set-id> [--title T]         replica of Alina's reference "Coffee" pin (8 items, 3x3, caps line in the centre)
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
# v2 (Alina's feedback): few BIG elements (default 9 = 3x3, tall 12 = 4x3) in a loose grid on visible
# cold-press cotton paper; studio line "JALINA.ART" on top, set name big in DM Serif, "N PNG · 300 DPI".
# real CC0 paper scans (ambientCG, assets/LICENSES.md), prepared by paper_real.py: 1 cold-press, 2 smooth cotton, 3 creamy handmade
PAPERS = {1: ('paper-1.jpg', '#F6F5F2'), 2: ('paper-2.jpg', '#F8F5F0'), 3: ('paper-3.jpg', '#F7F0E4')}
# tiny bits / scatters that read badly when shown large
TINY = re.compile(r'confetti|petals|dots|sprinkles|scatter|footprint|bone|crumbs|sparkle|cluster', re.I)


def ink(path):
    """Fraction of the trimmed bbox covered by paint (alpha): dense = solid, recognisable object."""
    import numpy as np
    from PIL import Image
    a = np.asarray(Image.open(path).getchannel('A').resize((64, 64)))
    return max(0.2, min(0.95, float((a > 40).mean())))


KINDS = [('vehicle', r'truck|car\\b|sleigh'), ('house', r'house|village'), ('plant', r'tree|fern|palm|plant|bush|leaves'), ('gift', r'gift|present|box'), ('ornament', r'bauble|ornament'),
         ('cake', r'cake|cupcake|cookie'), ('balloon', r'balloon'), ('hat', r'hat\b|party-hat'),
         ('wreath', r'wreath'), ('drink', r'mug|cup|cocoa|coffee|tea'), ('bouquet', r'bouquet|posy|arrangement'),
         ('egg', r'egg')]


def noun(fname):
    """Subject type of an element (tree, gift, ornament...; else the last word of its name), for variety."""
    for k, rx in KINDS:
        if re.search(rx, fname, re.I):
            return k
    w = pretty(fname).split()
    return re.sub(r'(es|s)$', '', w[-1]) if w else fname


HERO_RX = [r'bouquet|arrangement', r'(?<!cup)cake', r'house|village', r'wreath', r'christmas-tree|-tree\b', r'truck|sleigh']
LARGE_RX = re.compile(r'cake|bouquet|arrangement|house|wreath|tree|truck|stack|volcano|vase|hugging|peony|rose\b', re.I)
SMALL_RX = re.compile(r'mitten|candy|star|bud|leaf|sprig|hat\b|heart|horn|bone|cupcake|snowflake|berry|butterfly|moth|'
                      r'olive|bauble|ornament|balloon|grapes|anemone|dahlia|calla', re.I)


def pick_showcase(els, n):
    """Hero (most iconic: bouquet / cake / house / wreath / tree...) + the most recognisable rest:
    no lettering/garlands/tiny bits, dense (solid) objects first, one per subject type while possible."""
    ok = [e for e in els if not SKIP.search(e[0]) and not TINY.search(e[0]) and 0.5 <= e[3] <= 1.8]
    if len(ok) < n:
        ok += [e for e in els if e not in ok and not SKIP.search(e[0]) and 0.4 <= e[3] <= 2.4]
    ranked = sorted(ok, key=lambda e: -ink(e[1]))
    hero = next((e for rx in HERO_RX for e in ranked if re.search(rx, e[0], re.I)), ranked[0])
    out, seen = [hero], {noun(hero[0])}
    for e in ranked:
        if len(out) == n:
            break
        if e not in out and noun(e[0]) not in seen:
            out.append(e); seen.add(noun(e[0]))
    for e in ranked:
        if len(out) == n:
            break
        if e not in out:
            out.append(e)
    return out  # out[0] = hero


def sheet_sizes(els):
    """Relative 'diameter' per element: hero 1.85, 2-3 naturally large ones 1.3, small things 0.86,
    rest 1.0; sparse paintings get a little more room. Largest/smallest capped at 2.3."""
    fills = [ink(e[1]) for e in els]
    tier = [1.0] * len(els)
    tier[0] = 1.85
    big = [i for i in range(1, len(els)) if LARGE_RX.search(els[i][0])]
    big = (big + sorted((i for i in range(1, len(els)) if i not in big), key=lambda i: -fills[i]))[:3 if len(els) >= 9 else 2]
    for i in big:
        tier[i] = 1.3
    for i in range(1, len(els)):
        if i not in big and SMALL_RX.search(els[i][0]):
            tier[i] = 0.86
    d = [t / fills[i] ** 0.2 for i, t in enumerate(tier)]
    lo = max(d) / 2.3
    return [max(x, lo) for x in d]


def organic_layout(ars, ds, bw, bh, gap, masks=None, seeds=16):
    """Curated-sheet placement inside a bw x bh box: hero (index 0) centred a little above the middle,
    the others (largest first) at the sampled spot with the most clearance, then relaxed. Bounding boxes
    never overlap (>= gap apart) and stay inside the box. The global size is binary-searched per seed;
    the winner has the biggest elements, then the most even whitespace (smallest large holes)."""
    import numpy as np
    n = len(ars)
    order = [0] + sorted(range(1, n), key=lambda i: -ds[i])
    gx, gy = np.meshgrid(np.arange(0, bw, 22) + 11, np.arange(0, bh, 22) + 11)
    gx, gy = gx.ravel(), gy.ravel()
    # painted cells of each element (16x16 alpha grid) -> whitespace is measured to real paint, not bboxes
    mk = [np.argwhere(m) for m in masks] if masks is not None else [np.argwhere(np.ones((16, 16), bool))] * n

    def mhole(cx, cy, W_, H_):
        px = np.concatenate([cx[i] - W_[i] / 2 + (mk[i][:, 1] + .5) / 16 * W_[i] for i in range(n)])
        py = np.concatenate([cy[i] - H_[i] / 2 + (mk[i][:, 0] + .5) / 16 * H_[i] for i in range(n)])
        d = np.sqrt((gx[:, None] - px[None]) ** 2 + (gy[:, None] - py[None]) ** 2).min(1)
        return np.percentile(d, 97)

    def dims(i, s):
        return ds[i] * s * math.sqrt(ars[i]), ds[i] * s / math.sqrt(ars[i])

    def place(s, rng):
        W_ = np.array([dims(i, s)[0] for i in range(n)]); H_ = np.array([dims(i, s)[1] for i in range(n)])
        if W_.max() > bw or H_.max() > bh:
            return None
        cx = np.full(n, np.nan); cy = np.full(n, np.nan)
        h0 = order[0]
        cx[h0] = bw / 2 + rng.uniform(-0.06, 0.06) * bw
        cy[h0] = bh * rng.uniform(0.36, 0.5)
        cx[h0] = min(max(cx[h0], W_[h0] / 2), bw - W_[h0] / 2); cy[h0] = min(max(cy[h0], H_[h0] / 2), bh - H_[h0] / 2)

        def clear(i, x, y, done):
            # gap between rect i at (x,y) and each placed rect (negative = overlap)
            j = np.array(done)
            sx = np.abs(x[:, None] - cx[j][None]) - (W_[i] + W_[j][None]) / 2
            sy = np.abs(y[:, None] - cy[j][None]) - (H_[i] + H_[j][None]) / 2
            return np.maximum(sx, sy).min(1)

        done = [h0]
        for i in order[1:]:
            x = rng.uniform(W_[i] / 2, bw - W_[i] / 2, 1500); y = rng.uniform(H_[i] / 2, bh - H_[i] / 2, 1500)
            c = clear(i, x, y, done)
            k = int(np.argmax(c))
            if c[k] < gap:
                return None
            cx[i], cy[i] = x[k], y[k]; done.append(i)
        for _ in range(25):  # relax: nudge each non-hero element toward more clearance
            for i in order[1:]:
                oth = [j for j in done if j != i]
                x = np.clip(cx[i] + rng.normal(0, 18, 30), W_[i] / 2, bw - W_[i] / 2)
                y = np.clip(cy[i] + rng.normal(0, 18, 30), H_[i] / 2, bh - H_[i] / 2)
                x = np.append(x, cx[i]); y = np.append(y, cy[i])
                k = int(np.argmax(clear(i, x, y, oth)))
                cx[i], cy[i] = x[k], y[k]
        return cx, cy, W_, H_

    best = None
    for seed in range(seeds):
        rng = np.random.default_rng(seed)
        lo, hi, got = 50, 900, None
        for _ in range(11):
            s = (lo + hi) / 2
            r = None
            for _ in range(6):
                r = place(s, rng)
                if r: break
            if r: lo, got = s, r
            else: hi = s
        if not got:
            continue
        cx, cy, W_, H_ = got
        # whitespace evenness: distance from each free grid point to the nearest element
        hole = mhole(cx, cy, W_, H_)
        # vertical balance: painted mass centre near the box centre
        cm = (cy * W_ * H_).sum() / (W_ * H_).sum()
        score = lo * 1.0 - 1.5 * hole - 0.25 * abs(cm - bh / 2)
        if best is None or score > best[0]:
            best = (score, (cx.copy(), cy.copy(), W_.copy(), H_.copy()))
    cx, cy, W_, H_ = best[1]
    def holes():
        return None, mhole(cx, cy, W_, H_)

    def ok_at(i, x, y):
        j = np.array([k for k in range(n) if k != i])
        return np.maximum(np.abs(x - cx[j]) - (W_[i] + W_[j]) / 2, np.abs(y - cy[j]) - (H_[i] + H_[j]) / 2).min() >= gap

    # re-seat each non-hero element (smallest first) where it best evens out the whitespace
    rng = np.random.default_rng(99)
    for _ in range(2):
        for i in sorted(range(1, n), key=lambda i: W_[i] * H_[i]):
            best_h, best_xy = holes()[1], (cx[i], cy[i])
            ox, oy = cx[i], cy[i]
            for x, y in zip(rng.uniform(W_[i] / 2, bw - W_[i] / 2, 70), rng.uniform(H_[i] / 2, bh - H_[i] / 2, 70)):
                if not ok_at(i, x, y):
                    continue
                cx[i], cy[i] = x, y
                h = holes()[1]
                if h < best_h - 1:
                    best_h, best_xy = h, (x, y)
                cx[i], cy[i] = ox, oy
            cx[i], cy[i] = best_xy
    # grow: let each element take up to +30% where its neighbourhood has room (fills holes organically)
    base = W_.copy()
    for _ in range(40):
        changed = False
        for i in range(n):
            f = 1.03
            w, h = W_[i] * f, H_[i] * f
            if w > base[i] * 1.3 or w > bw or h > bh:
                continue
            x = min(max(cx[i], w / 2), bw - w / 2); y = min(max(cy[i], h / 2), bh - h / 2)
            j = np.array([k for k in range(n) if k != i])
            g = np.maximum(np.abs(x - cx[j]) - (w + W_[j]) / 2, np.abs(y - cy[j]) - (h + H_[j]) / 2).min()
            if g >= gap:
                cx[i], cy[i], W_[i], H_[i] = x, y, w, h; changed = True
        if not changed:
            break
    return [(cx[i] - W_[i] / 2, cy[i] - H_[i] / 2, W_[i], H_[i]) for i in range(n)]


def tmpl_s(sid, n, title=None, size=(1000, 1500), paper=1):
    w_, h_ = size
    s = SHOP[sid]
    n = n or (12 if h_ > 1600 else 9)
    els = pick_showcase(set_elements(sid), n)
    title = title or s['name']
    sub = f'{s["count"]} PNG · 300 DPI'
    top, bottom, side = 268, 34, 30
    bw, bh = w_ - 2 * side, h_ - top - bottom
    from PIL import Image
    import numpy as np
    masks = [np.asarray(Image.open(e[1]).getchannel('A').resize((16, 16))) > 60 for e in els]
    pos = organic_layout([e[3] for e in els], sheet_sizes(els), bw, bh, 14, masks)
    body = (f'<div class=studio>JALINA.ART</div><div class=ttl data-fit=96>{esc(title)}</div>'
            f'<div class=sub>{esc(sub)}</div>')
    for e, (x, y, w, h) in zip(els, pos):
        body += (f'<div class=abs style="left:{side+x:.0f}px;top:{top+y:.0f}px;width:{w:.0f}px;height:{h:.0f}px">'
                 f'<img src="file://{e[2]}"></div>')
    css = f'''@font-face{{font-family:S;src:url(file://{FONTS}/DMSerifDisplay-400-latin.woff2)}}
@font-face{{font-family:M;src:url(file://{FONTS}/Manrope-400-latin.woff2)}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:{w_}px;height:{h_}px;overflow:hidden}}
body{{position:relative;background:{PAPERS[paper][1]} url(file://{os.path.join(HERE, 'assets', PAPERS[paper][0])}) 0 0/{w_}px {h_}px no-repeat;color:#3E3D3C}}
.abs{{position:absolute}} .abs img{{width:100%;height:100%;object-fit:contain;display:block;mix-blend-mode:multiply}}
.studio{{position:absolute;top:50px;left:0;right:0;text-align:center;font-family:M;font-size:25px;
letter-spacing:.34em;padding-left:.34em;color:#8E8A93}}
.ttl{{position:absolute;top:88px;left:40px;right:40px;text-align:center;font-family:S;line-height:1.08;
white-space:nowrap;color:#3E3D3C;letter-spacing:.01em}}
.sub{{position:absolute;top:202px;left:0;right:0;text-align:center;font-family:M;font-size:19px;
letter-spacing:.3em;padding-left:.3em;color:#8E8A93}}'''
    doc = (f'<!doctype html><html data-w={w_} data-h={h_}><head><meta charset=utf-8><style>{css}</style></head>'
           f'<body>{body}{SHEET_FIT}</body></html>')
    return doc, [e[0] for e in els]


SHEET_FIT = ('<script>document.fonts.ready.then(()=>{const e=document.querySelector(".ttl");let s=+e.dataset.fit;'
             'e.style.fontSize=s+"px";while(e.scrollWidth>e.clientWidth&&s>40){s-=2;e.style.fontSize=s+"px"}'
             'document.body.dataset.ready=1})</script>')


# ---------- R: one-to-one replica of Alina's reference "Coffee" pin (Nov 12 2026) ----------
# Measured on the 1000x1500 reference: studio caps y40-67 (x305-701), title cap y97-156, 3x3 grid with the
# middle cell of row 2 replaced by a small caps line (y658-677); side columns centred at x~165 / ~830,
# middle column x~500 and ~75 px LOWER than the sides in every row; items fill ~310x340 by their own shape;
# outer items 14-40 px from the edges. 9 items (the middle one of row 2 sits under the caps line). Fonts: Bodoni Moda (OFL, assets/fonts). Paper: assets/paper-r.jpg.
FONT_DIR = os.path.join(HERE, 'assets', 'fonts')
R_COLS = (165, 503, 830)
R_SIDE_Y = (362, 792, 1214)
R_MID_Y = (440, 870, 1280)
R_SLATE = '#7D8CAB'


def tmpl_r(sid, title=None):
    s = SHOP[sid]
    els = pick_showcase(set_elements(sid), 9)  # the reference shows 9 items (8 around + 1 under the caps line)
    title = title or s['name']
    mid = f'{s["count"]} PNG · 300 DPI'
    # middle-column cells hold the reference's tall/square items; give them the least wide picks
    mids = sorted(range(len(els)), key=lambda i: els[i][3])[:3]
    order = mids + [i for i in range(len(els)) if i not in mids]
    slots = [(1, 0), (1, 1), (1, 2), (0, 0), (2, 0), (0, 1), (2, 1), (0, 2), (2, 2)]
    body = (f'<div class=studio>JALINA.ART</div><div class=ttl><span>{esc(title)}</span></div>'
            f'<div class=mid>{esc(mid)}</div>')
    used = []
    for i, (c, r) in zip(order, slots):
        e = els[i]
        bw_, bh_ = (325, 350) if c != 1 else (315, 318 if r == 1 else 335)
        k = min(bw_ / e[3], bh_)  # height that fits the box
        h = k; w = k * e[3]
        cx = R_COLS[c]
        cy = R_MID_Y[r] if c == 1 else R_SIDE_Y[r]
        x = min(max(cx - w / 2, 14), 1000 - 14 - w)
        y = cy - h / 2
        if c == 1 and r == 1:
            y = max(y, 705)  # below the middle caps line
        body += (f'<div class=abs style="left:{x:.0f}px;top:{y:.0f}px;width:{w:.0f}px;height:{h:.0f}px">'
                 f'<img src="file://{e[2]}"></div>')
        used.append(e[0])
    bm = os.path.join(FONT_DIR, 'BodoniModa-VariableFont_opsz,wght.ttf')
    css = f'''@font-face{{font-family:B;src:url("file://{bm}")}}
*{{box-sizing:border-box;margin:0;padding:0}}
html,body{{width:1000px;height:1500px;overflow:hidden}}
body{{position:relative;background:#F7F7F6 url(file://{os.path.join(HERE, 'assets', 'paper-r.jpg')}) 0 0/1000px 2000px no-repeat;
font-family:B;font-variant-ligatures:none;font-kerning:normal}}
.abs{{position:absolute}} .abs img{{width:100%;height:100%;object-fit:contain;display:block;mix-blend-mode:multiply}}
.studio,.ttl,.mid{{position:absolute;left:0;right:0;text-align:center;white-space:nowrap;line-height:1}}
.studio{{top:32px;font-size:40px;font-weight:450;font-variation-settings:'opsz' 24;color:{R_SLATE};letter-spacing:.01em}}
.ttl{{top:84px;font-size:84px;font-weight:450;font-variation-settings:'opsz' 40;color:#3C3C3C;letter-spacing:.005em}}
.mid{{top:651px;left:3px;font-size:29px;font-weight:500;font-variation-settings:'opsz' 28;color:{R_SLATE};letter-spacing:.01em}}'''
    doc = (f'<!doctype html><html data-w=1000 data-h=1500><head><meta charset=utf-8><style>{css}</style></head>'
           f'<body>{body}<script>document.fonts.ready.then(()=>{{const e=document.querySelector(".ttl");let s=84;const t=e.firstChild;'
           f'while(t.offsetWidth>940&&s>50){{s-=2;e.style.fontSize=s+"px"}}document.body.dataset.ready=1}})</script></body></html>')
    return doc, used


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
    ap.add_argument('template', choices=['A', 'B', 'C', 'D', 'S', 'R', 'list'])
    ap.add_argument('target')
    ap.add_argument('index', nargs='?', type=int)
    ap.add_argument('-n', '--n', type=int, default=None, help='A: number of elements (9-20, default 12); S: 6-12 (default 9, tall 12)')
    ap.add_argument('--size', default='1000x1500', help='S: 1000x1500 or 1000x2000')
    ap.add_argument('--paper', type=int, choices=[1, 2, 3], default=1, help='S: 1 cold-press, 2 smooth cotton, 3 creamy handmade')
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
    if a.template == 'R':
        doc, used = tmpl_r(a.target, a.title)
        name = f'v2-R-{a.target}'
    elif a.template == 'S':
        sw, sh = map(int, a.size.lower().split('x'))
        doc, used = tmpl_s(a.target, a.n and max(6, min(12, a.n)), a.title, (sw, sh), a.paper)
        name = f'v2-S-{a.target}' + ('-tall' if sh > 1600 else '') + (f'-paper{a.paper}' if a.paper != 1 else '')
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
