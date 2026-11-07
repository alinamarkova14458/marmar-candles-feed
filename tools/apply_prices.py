#!/usr/bin/env python3
"""Apply a retail price list to WooCommerce (products and variations).

CSV columns: ID, Type (simple|variation), Parent, Regular price.
Before writing, saves the current regular prices to <csv>.backup.json for rollback.
Auth: WP_USER + WP_APP_PASSWORD (administrator application password). Never prints secrets.
The host (area.lv) throttles bursts, so requests are paced and retried.

Usage:
    python3 tools/apply_prices.py pricing/2026-11-08-retail-phase1.csv [--dry-run]
"""
import json, os, sys, time
import pandas as pd, requests

B = "https://marmarcandles.com/wp-json/wc/v3"
H = {"User-Agent": "marmar-admin-script"}
AUTH = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])


def call(method, url, body=None):
    for attempt in range(8):
        try:
            r = requests.request(method, url, json=body, auth=AUTH, timeout=180, headers=H)
            if r.ok and "json" in (r.headers.get("content-type") or ""):
                return r.json()
            print(f"  retry {attempt} HTTP {r.status_code}", flush=True)
        except Exception as e:  # network hiccup / timeout
            print(f"  retry {attempt} {type(e).__name__}", flush=True)
        time.sleep(60)
    return None


def main():
    path = sys.argv[1]
    dry = "--dry-run" in sys.argv
    df = pd.read_csv(path)
    df["price"] = df["Regular price"].map(lambda x: f"{float(x):.2f}")
    backup, ok, failed = {}, 0, []
    for _, r in df[df.Type == "simple"].iterrows():
        cur = call("GET", f"{B}/products/{int(r.ID)}?_fields=id,regular_price")
        backup[str(int(r.ID))] = cur and cur.get("regular_price")
        if not dry:
            res = call("PUT", f"{B}/products/{int(r.ID)}", {"regular_price": r.price})
            ok += bool(res) or 0
            if not res:
                failed.append(int(r.ID))
        time.sleep(5)
    for parent, g in df[df.Type == "variation"].groupby("Parent"):
        page, cur = 1, {}
        while True:
            vs = call("GET", f"{B}/products/{int(parent)}/variations?per_page=100&page={page}&status=any&_fields=id,regular_price")
            if not vs:
                break
            cur.update({str(v["id"]): v["regular_price"] for v in vs})
            page += 1
            time.sleep(5)
        backup.update({str(int(i)): cur.get(str(int(i))) for i in g.ID})
        json.dump(backup, open(path + ".backup.json", "w"))
        if dry:
            continue
        rows = list(g.itertuples())
        for i in range(0, len(rows), 25):
            res = call("POST", f"{B}/products/{int(parent)}/variations/batch",
                       {"update": [{"id": int(x.ID), "regular_price": x.price} for x in rows[i:i + 25]]})
            if res:
                ok += sum(1 for u in res.get("update", []) if not u.get("error"))
            else:
                failed += [int(x.ID) for x in rows[i:i + 25]]
            time.sleep(6)
        print(f"parent {int(parent)}: {len(rows)} rows, total ok {ok}", flush=True)
    json.dump(backup, open(path + ".backup.json", "w"))
    print(f"DONE ok={ok} failed={len(failed)} {failed[:20]}", flush=True)


if __name__ == "__main__":
    main()
