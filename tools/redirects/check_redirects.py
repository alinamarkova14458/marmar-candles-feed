#!/usr/bin/env python3
"""After the redirects are live: check every old URL does exactly ONE 301 to a page that answers 200.

Usage: python3 tools/redirects/check_redirects.py seo/redirects/rankmath-redirects-2026-11.csv [--www]
Exit code 0 = all good, 1 = problems (listed).
"""
import csv, sys, concurrent.futures as cf
import requests

H = {"User-Agent": "Mozilla/5.0 (compatible; MarMarRedirectCheck/1.0)"}

def check(row, host):
    src = f"https://{host}/{row['source']}/"
    try:
        r = requests.get(src, headers=H, allow_redirects=False, timeout=60)
        if r.status_code != 301:
            return src, f"ожидался 301, получен {r.status_code}"
        loc = r.headers.get("location", "")
        if loc.rstrip("/") != row["destination"].rstrip("/"):
            return src, f"301 ведёт не туда: {loc}"
        d = requests.get(loc, headers=H, allow_redirects=False, timeout=60)
        if d.status_code != 200:
            return src, f"цель отвечает {d.status_code}"
    except Exception as e:
        return src, f"ошибка: {type(e).__name__}"
    return src, None

def main():
    rows = list(csv.DictReader(open(sys.argv[1])))
    hosts = ["marmarcandles.com"] + (["www.marmarcandles.com"] if "--www" in sys.argv else [])
    jobs = [(r, h) for r in rows for h in hosts]
    with cf.ThreadPoolExecutor(4) as ex:
        res = list(ex.map(lambda j: check(*j), jobs))
    bad = [(u, e) for u, e in res if e]
    for u, e in bad:
        print(f"[FAIL] {u}: {e}")
    print(f"{len(res) - len(bad)}/{len(res)} OK")
    return 1 if bad else 0

if __name__ == "__main__":
    sys.exit(main())
