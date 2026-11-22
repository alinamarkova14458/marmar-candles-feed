#!/usr/bin/env python3
"""Collect the data files for Alina's personal dashboard.

Writes CSV files to the output directory (no customer names, addresses or
e-mails): Search Console, WooCommerce orders, Merchant Center, Instagram plan.

Usage:
    pip install -q google-auth requests
    python3 tools/dashboard_data.py OUT_DIR
Needs GOOGLE_SERVICE_ACCOUNT_JSON, WP_USER, WP_APP_PASSWORD.
"""
import collections
import csv
import datetime as dt
import glob
import json
import os
import sys

import requests
from google.auth.transport.requests import AuthorizedSession
from google.oauth2 import service_account

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GSC = "https://searchconsole.googleapis.com/webmasters/v3/sites/sc-domain%3Amarmarcandles.com/searchAnalytics/query"
MERCHANT = "accounts/5495474688"
SITE = "https://marmarcandles.com"
SOURCES = {"organic": "Поиск Google", "typein": "Прямой заход", "referral": "Ссылка с сайта",
           "utm": "Реклама / рассылка", "admin": "Создан вручную"}


def write(out, name, header, rows):
    with open(os.path.join(out, name), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f, lineterminator="\n")
        w.writerow(header)
        w.writerows(rows)
    print(f"{name}: {len(rows)} rows")


def gsc(s, start, end, dims, limit=1000):
    r = s.post(GSC, json={"startDate": str(start), "endDate": str(end), "dimensions": dims, "rowLimit": limit}, timeout=60)
    r.raise_for_status()
    return r.json().get("rows", [])


def search_console(s, out):
    today = dt.date.today()
    daily = gsc(s, today - dt.timedelta(days=180), today, ["date"])
    write(out, "gsc_daily.csv", ["date", "clicks", "impressions", "position"],
          [[r["keys"][0], r["clicks"], r["impressions"], round(r["position"], 1)] for r in daily])
    last = dt.date.fromisoformat(daily[-1]["keys"][0])
    cur = (last - dt.timedelta(days=27), last)
    prev = (last - dt.timedelta(days=55), last - dt.timedelta(days=28))
    old = {r["keys"][0]: r for r in gsc(s, *prev, ["query"])}
    rows = []
    for r in sorted(gsc(s, *cur, ["query"]), key=lambda r: -r["impressions"])[:30]:
        o = old.get(r["keys"][0], {})
        rows.append([r["keys"][0], r["impressions"], r["clicks"], round(r["position"], 1),
                     o.get("impressions", 0), round(o["position"], 1) if o else ""])
    write(out, "gsc_queries.csv", ["query", "impressions", "clicks", "position", "prev_impressions", "prev_position"], rows)
    pages = sorted(gsc(s, *cur, ["page"], 200), key=lambda r: -r["impressions"])[:15]
    write(out, "gsc_pages.csv", ["page", "impressions", "clicks", "position"],
          [[r["keys"][0].replace(SITE, "") or "/", r["impressions"], r["clicks"], round(r["position"], 1)] for r in pages])


def orders(out):
    auth = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])
    after = (dt.date.today() - dt.timedelta(days=180)).isoformat() + "T00:00:00"
    rows, page = [], 1
    while True:
        r = requests.get(SITE + "/wp-json/wc/v3/orders", auth=auth, timeout=60, params={
            "per_page": 100, "page": page, "after": after, "status": "processing,completed,on-hold",
            "_fields": "id,status,date_created,total,meta_data,fee_lines"})
        r.raise_for_status()
        batch = r.json()
        for o in batch:
            meta = {m["key"]: m["value"] for m in o["meta_data"] if isinstance(m.get("value"), str)}
            src = meta.get("_wc_order_attribution_source_type", "")
            utm = meta.get("_wc_order_attribution_utm_source", "")
            label = SOURCES.get(src, "Неизвестно")
            if src == "organic" and "google" not in utm.lower():
                label = "Другой поиск"
            if "instagram" in utm.lower() or "ig" == utm.lower() or "facebook" in utm.lower():
                label = "Instagram / Facebook"
            express = any("steidzam" in f["name"].lower() for f in o.get("fee_lines", []))
            rows.append([o["id"], o["date_created"][:10], o["status"], o["total"], label, "yes" if express else "no"])
        if len(batch) < 100:
            break
        page += 1
    rows.sort(key=lambda r: r[1])
    write(out, "orders.csv", ["order", "date", "status", "total", "source", "express"], rows)


def merchant(s, out):
    products, token = [], None
    while True:
        r = s.get(f"https://merchantapi.googleapis.com/products/v1/{MERCHANT}/products",
                  params={"pageSize": 250, **({"pageToken": token} if token else {})}, timeout=60)
        r.raise_for_status()
        d = r.json()
        products += d.get("products", [])
        token = d.get("nextPageToken")
        if not token:
            break
    status = collections.Counter()
    for p in products:
        st = "Без статуса"
        for d in p.get("productStatus", {}).get("destinationStatuses", []):
            if d.get("reportingContext") != "FREE_LISTINGS":
                continue
            if "LV" in d.get("approvedCountries", []):
                st = "Одобрено"
            elif "LV" in d.get("pendingCountries", []):
                st = "На проверке"
            elif "LV" in d.get("disapprovedCountries", []):
                st = "Отклонено"
        status[st] += 1
    write(out, "merchant.csv", ["status", "products"], sorted(status.items(), key=lambda kv: -kv[1]))


def instagram(out):
    kinds = {"image": "фото", "photo": "фото", "carousel": "карусель", "reel": "рилс", "story": "сторис"}
    with open(os.path.join(ROOT, "social/instagram/published.json"), encoding="utf-8") as f:
        done = json.load(f)
    rows = []
    for path in glob.glob(os.path.join(ROOT, "social/instagram/queue/*.json")):
        with open(path, encoding="utf-8") as f:
            for p in json.load(f):
                if p["id"] in done:
                    continue
                text = " ".join((p.get("caption") or "").split("\n—")[0].split())
                if not text:
                    text = p["id"][11:].replace("story-", "").replace("-", " ")
                rows.append([p["id"], p["date"], kinds.get(p.get("type"), p.get("type", "")), text[:90]])
    rows.sort(key=lambda r: r[1])
    write(out, "instagram.csv", ["id", "date", "type", "text"], rows)


def main():
    out = sys.argv[1]
    os.makedirs(out, exist_ok=True)
    creds = service_account.Credentials.from_service_account_info(
        json.loads(os.environ["GOOGLE_SERVICE_ACCOUNT_JSON"]),
        scopes=["https://www.googleapis.com/auth/webmasters.readonly", "https://www.googleapis.com/auth/content"])
    s = AuthorizedSession(creds)
    search_console(s, out)
    orders(out)
    merchant(s, out)
    instagram(out)


if __name__ == "__main__":
    main()
