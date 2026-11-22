"""Pinterest bulk upload rejects rows whose Title repeats (error 'Multiple rows with the same title', 22.11.2026).
Usage: python3 fix_titles.py <error_csv_from_pinterest> <file2.csv>  -> retry + file2 with globally unique titles."""
import csv, re, sys
TPL = ['{b} – {n} PNG', '{b}: {n} Transparent PNGs', '{b} for Cards & Gift Tags', '{b} for Canva and Cricut',
       '{b} – 300 DPI PNG Set', '{b} for Invitations & Printables', '{b} PNG Bundle', '{b} for Stickers & Mugs',
       '{b} – Instant Download', '{b} for Crafters', '{b} – Commercial Use PNG', '{b} Set for DIY Projects',
       '{b} for Holiday Cards', '{b} – Watercolor PNG Set', '{b} for Party Decor', '{b} for Planners & Journals',
       '{b} – Print at Home', '{b} for Tumblers & Shirts', '{b} – Small Shop Set', '{b} for Scrapbooking',
       '{b} – {n} Elements', '{b} for Last-Minute Gifts', '{b} – Cozy Season Set', '{b} for Labels & Tags']
err, f2 = sys.argv[1], sys.argv[2]
rows = list(csv.DictReader(open(err, encoding='utf-8-sig')))
ok = [r for r in rows if not r.get('error')]
bad = [r for r in rows if r.get('error')]
rows2 = list(csv.DictReader(open(f2, encoding='utf-8')))
used = {r['Title'] for r in ok}
base = {}
for r in ok + bad + rows2:
    code = r['Link'].split('?')[0]
    m = re.match(r'(.+? Clipart) – (\d+) PNG$', r['Title'])
    if m and code not in base: base[code] = m.groups()
for r in bad + rows2:
    code = r['Link'].split('?')[0]
    b, n = base[code]
    for t in TPL:
        title = t.format(b=b, n=n)
        if title not in used: break
    else: raise SystemExit('ran out of titles for ' + b)
    r['Title'] = title; used.add(title)
COLS = ['Title', 'Media URL', 'Pinterest board', 'Thumbnail', 'Description', 'Link', 'Publish date', 'Keywords']
def write(name, rs):
    with open(name, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=COLS, extrasaction='ignore', lineterminator='\n'); w.writeheader(); w.writerows(rs)
    print(name, len(rs))
write('jalina-payhip-retry-2026-11-26_12-02.csv', bad)
write(f2, rows2)
print('accepted:', len(ok), 'unique titles total:', len(used), 'of', len(ok) + len(bad) + len(rows2))
