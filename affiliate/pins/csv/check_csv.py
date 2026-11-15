"""Check the Pinterest bulk CSV files against Alina's rules (see build_csv.py docstring).

  python3 check_csv.py jalina-2026-11-19_12-02.csv jalina-2026-12-03_12-18.csv [--live]

--live also requests every Link (path without UTM) and every Media URL on the live site and checks that an
#anchor (if any) exists in the page. Exit code 1 if anything breaks a rule.
"""
import csv, datetime as dt, os, re, sys, urllib.parse, urllib.request
from collections import defaultdict

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from build_csv import EXISTING, jal_cap, COLS  # noqa: E402

BLOG = '/home/user/jalina-blog'
BOARDS = {'Fonts & Clipart for Canva and Cricut', 'Cute Clipart PNG for Crafts', 'Canva Fonts & Font Pairings',
          'Holiday Clipart & Fonts', 'Watercolor Birthday & Holiday Clipart'}
REDIRECTED = {'gingerbread-clipart', 'christmas-cookie-clipart', 'candy-cane-clipart', 'hot-cocoa-clipart'}
errs = []
warns = []


def err(m):
    errs.append(m)


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    live = '--live' in sys.argv
    test_header = next(csv.reader(open(os.path.join(HERE, 'jalina-test-2026-11-17.csv'), encoding='utf-8')))
    rows = []
    for f in args:
        with open(f, encoding='utf-8', newline='') as fh:
            r = csv.reader(fh)
            head = next(r)
            if head != test_header or head != COLS:
                err(f'{f}: header {head} != test file {test_header}')
            for i, row in enumerate(r, 2):
                if len(row) != len(head):
                    err(f'{f}:{i}: {len(row)} fields')
                    continue
                d = dict(zip(head, row)); d['_f'] = f'{os.path.basename(f)}:{i}'
                rows.append(d)
        if len(rows) > 200:
            pass
        n = sum(1 for _ in open(f, encoding='utf-8')) - 1
        if n > 200:
            err(f'{f}: {n} rows > 200 (Pinterest bulk limit)')
    redirects = set()
    rp = os.path.join(BLOG, 'dist', '_redirects')
    if os.path.exists(rp):
        for line in open(rp):
            p = line.split()
            if len(p) >= 2 and p[0].strip('/') != p[1].strip('/'):  # skip "/x -> /x/" slash fixes
                redirects.add(p[0].strip('/'))
    descs, per_day = defaultdict(list), defaultdict(list)
    for d in rows:
        t = d['_f']
        if not d['Title'] or len(d['Title']) > 100:
            err(f'{t}: title length {len(d["Title"])}')
        if not d['Description'] or len(d['Description']) > 500:
            err(f'{t}: description length {len(d["Description"])}')
        if d['Thumbnail']:
            err(f'{t}: Thumbnail must be empty for images')
        if d['Pinterest board'] not in BOARDS:
            err(f'{t}: board {d["Pinterest board"]!r}')
        if re.search(r'jalina(?!\.art)|Jalina Art', d['Title'] + d['Description']):
            err(f'{t}: brand spelled other than jalina.art')
        descs[d['Description']].append(t)
        try:
            ts = dt.datetime.strptime(d['Publish date'], '%Y-%m-%dT%H:%M:%S')
        except ValueError:
            err(f'{t}: bad Publish date {d["Publish date"]}'); continue
        mins = ts.hour * 60 + ts.minute
        if not (13 * 60 <= mins <= 21 * 60) or ts.minute % 20:
            err(f'{t}: {ts} outside 13:00–21:00 UTC grid of 20 min')
        if not (dt.date(2026, 11, 19) <= ts.date() <= dt.date(2026, 12, 18)):
            err(f'{t}: date {ts.date()} out of range')
        u = urllib.parse.urlparse(d['Link'])
        q = urllib.parse.parse_qs(u.query)
        m = urllib.parse.urlparse(d['Media URL'])
        if m.netloc != 'jalina.art' or not m.path.startswith('/pins/csv/') or not m.path.endswith('.jpg'):
            err(f'{t}: media {d["Media URL"]}')
        local = os.path.join(BLOG, 'public', m.path.lstrip('/'))
        if not os.path.exists(local):
            err(f'{t}: media file missing {local}')
        elif os.path.getsize(local) >= 1_000_000:
            err(f'{t}: media {local} >= 1 MB')
        kind = 'payhip' if u.netloc == 'payhip.com' else 'jal'
        if kind == 'payhip':
            if d['Pinterest board'] != 'Watercolor Birthday & Holiday Clipart':
                err(f'{t}: payhip pin on wrong board')
            if q.get('utm_campaign') != ['own-payhip'] or not re.fullmatch(r'\d\d-[a-z-]+-v2-\d', q.get('utm_content', [''])[0]):
                err(f'{t}: payhip utm {u.query}')
        else:
            if u.netloc != 'jalina.art':
                err(f'{t}: link host {u.netloc}')
            if q.get('utm_source') != ['pinterest'] or q.get('utm_medium') != ['pin'] or q.get('utm_campaign') != [f'csv-{ts:%Y%m}'] or not q.get('utm_content'):
                err(f'{t}: utm {u.query}')
            slug = u.path.strip('/')
            if slug in REDIRECTED or slug in redirects:
                err(f'{t}: link to redirected slug {slug}')
            if not u.path.endswith('/'):
                err(f'{t}: path without trailing slash')
        per_day[ts.date()].append((kind, u.path if kind == 'jal' else u.path, ts, d))
    for desc, where in descs.items():
        if len(where) > 1:
            err(f'duplicate description in {where}')
    # per-day caps, slot collisions, URL frequency, image reuse
    url_days = defaultdict(list)
    for day, ex in EXISTING.items():
        for _, p in ex:
            url_days[p].append(day)
    img_days = defaultdict(list)
    for day in sorted(per_day):
        items = per_day[day]
        ex = EXISTING.get(day, [])
        jal = sum(1 for k, *_ in items if k == 'jal') + len(ex)  # Metricool pins all link to jalina.art
        pay = sum(1 for k, *_ in items if k == 'payhip')
        if jal > jal_cap(day):
            if jal - len(ex) == 0:  # only Metricool pins: not the CSV's doing, but report it
                warns.append(f'{day}: Metricool alone has {len(ex)} jalina.art pins > cap {jal_cap(day)} (CSV adds 0)')
            else:
                err(f'{day}: {jal} jalina.art pins incl. Metricool > cap {jal_cap(day)}')
        if not 3 <= pay <= 4:
            err(f'{day}: {pay} Payhip pins (want 3–4)')
        if jal + pay > 15:
            err(f'{day}: total {jal + pay} > 15')
        slots = [ts.strftime('%H:%M') for *_, ts, _ in [(k, p, ts, d) for k, p, ts, d in items]]
        ex_utc = [f'{int(t[:2]) - 2:02d}:{t[3:]}' for t, _ in ex]
        if len(set(slots)) != len(slots) or set(slots) & set(ex_utc):
            err(f'{day}: slot collision {sorted(slots)} / Metricool {ex_utc}')
        paths = [p for k, p, *_ in items if k == 'jal'] + [p for _, p in ex]
        if len(set(paths)) != len(paths):
            err(f'{day}: a jalina.art URL twice: {sorted(paths)}')
        for k, p, ts, d in items:
            if k == 'jal':
                url_days[p].append(day)
            img_days[d['Media URL']].append(day)
    for p, ds in url_days.items():
        ds.sort()
        for x in ds:
            n = sum(1 for y in ds if 0 <= (y - x).days < 7)
            if n > 3:
                err(f'{p}: {n} pins in the 7 days from {x}')
    for m, ds in img_days.items():
        ds.sort()
        for a, b in zip(ds, ds[1:]):
            if (b - a).days < 4:
                err(f'image {m} reused {a} -> {b}')
    if live:
        seen = {}
        for d in rows:
            u = urllib.parse.urlparse(d['Link'])
            for url in ({d['Media URL']} | ({f'https://{u.netloc}{u.path}'} if u.netloc == 'jalina.art' else set())):
                if url in seen:
                    continue
                try:
                    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (pin-check)'})
                    with urllib.request.urlopen(req, timeout=30) as r:
                        seen[url] = (r.status, r.read() if url.endswith('/') else b'')
                except Exception as e:
                    seen[url] = (getattr(e, 'code', str(e)), b'')
                if seen[url][0] != 200:
                    err(f'live {url}: {seen[url][0]}')
            if u.fragment and u.netloc == 'jalina.art':
                body = seen.get(f'https://{u.netloc}{u.path}', (0, b''))[1].decode('utf-8', 'ignore')
                if f'id="{u.fragment}"' not in body:
                    err(f'{d["_f"]}: anchor #{u.fragment} missing')
        print(f'live: {len(seen)} URLs requested')
    days = sorted(per_day)
    tot = {day: len(per_day[day]) + len(EXISTING.get(day, [])) for day in days}
    print(f'{len(rows)} rows, {len(days)} days, total/day incl. Metricool min {min(tot.values())} max {max(tot.values())}, '
          f'unique images {len(img_days)}')
    for w in warns:
        print('WARN', w)
    for e in errs:
        print('ERROR', e)
    print('OK' if not errs else f'{len(errs)} problems')
    sys.exit(1 if errs else 0)


if __name__ == '__main__':
    main()
