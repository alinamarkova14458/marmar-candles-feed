#!/usr/bin/env python3
"""Weekly SEO report for MarMar Candles: Search Console, Merchant Center, GA4.

Reads the service-account key from GOOGLE_SERVICE_ACCOUNT_JSON (never printed)
and prints a Markdown report in Russian to stdout.

Usage:
    pip install -q google-auth requests
    python3 tools/seo_report.py [--end YYYY-MM-DD]
"""
import argparse
import collections
import datetime as dt
import json
import os
import sys

from google.auth.transport.requests import AuthorizedSession
from google.oauth2 import service_account

GSC_SITE = "sc-domain:marmarcandles.com"
GA4_PROPERTY = "properties/336408410"
MERCHANT = "accounts/5495474688"
FEED_SOURCE = MERCHANT + "/dataSources/10790139142"
SCOPES = [
    "https://www.googleapis.com/auth/webmasters.readonly",
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/content",
]
SEASON_WORDS = {
    "Рождество": ("ziemassv", "ziemas sv", "jaungad", "egl", "advent", "christmas"),
    "Свадьбы": ("kāz", "kaz", "wedding", "līgav"),
}


def session() -> AuthorizedSession:
    raw = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not raw:
        sys.exit("GOOGLE_SERVICE_ACCOUNT_JSON is not set")
    creds = service_account.Credentials.from_service_account_info(json.loads(raw), scopes=SCOPES)
    return AuthorizedSession(creds)


# ---------- Search Console ----------
def gsc(s, start, end, dims, limit=1000):
    url = f"https://searchconsole.googleapis.com/webmasters/v3/sites/{GSC_SITE.replace(':', '%3A')}/searchAnalytics/query"
    body = {"startDate": str(start), "endDate": str(end), "dimensions": dims, "rowLimit": limit}
    r = s.post(url, json=body, timeout=60)
    r.raise_for_status()
    return r.json().get("rows", [])


def totals(rows):
    if not rows:
        return {"clicks": 0, "impressions": 0, "ctr": 0.0, "position": 0.0}
    return rows[0]


def pct(new, old):
    if old == 0:
        return "новое" if new else "—"
    return f"{(new - old) / old * 100:+.0f}%"


def fmt_pos(p):
    return f"{p:.1f}" if p else "—"


def gsc_section(s, cur, prev):
    out = []
    t_now = totals(gsc(s, *cur, []))
    t_old = totals(gsc(s, *prev, []))
    out.append("## Поиск Google (Search Console)\n")
    out.append("| | Эта неделя | Прошлая | Изменение |\n|---|---|---|---|")
    out.append(f"| Клики | {t_now['clicks']:.0f} | {t_old['clicks']:.0f} | {pct(t_now['clicks'], t_old['clicks'])} |")
    out.append(f"| Показы | {t_now['impressions']:.0f} | {t_old['impressions']:.0f} | {pct(t_now['impressions'], t_old['impressions'])} |")
    out.append(f"| CTR | {t_now['ctr']*100:.1f}% | {t_old['ctr']*100:.1f}% | |")
    out.append(f"| Средняя позиция | {fmt_pos(t_now['position'])} | {fmt_pos(t_old['position'])} | |\n")

    q_now = {r["keys"][0]: r for r in gsc(s, *cur, ["query"])}
    q_old = {r["keys"][0]: r for r in gsc(s, *prev, ["query"])}

    out.append("### Топ-10 запросов по кликам\n")
    out.append("| Запрос | Клики | Показы | Позиция | Было (позиция) |\n|---|---|---|---|---|")
    for q, r in sorted(q_now.items(), key=lambda kv: (-kv[1]["clicks"], -kv[1]["impressions"]))[:10]:
        old = q_old.get(q)
        out.append(f"| {q} | {r['clicks']:.0f} | {r['impressions']:.0f} | {fmt_pos(r['position'])} | {fmt_pos(old['position']) if old else 'нет'} |")

    movers = []
    for q in set(q_now) | set(q_old):
        a, b = q_now.get(q), q_old.get(q)
        movers.append((q, (a or {}).get("clicks", 0) - (b or {}).get("clicks", 0), a, b))
    up = [m for m in sorted(movers, key=lambda m: -m[1]) if m[1] > 0][:5]
    down = [m for m in sorted(movers, key=lambda m: m[1]) if m[1] < 0][:5]
    if up or down:
        out.append("\n### Что выросло и что упало (клики)\n")
        for q, d, a, b in up:
            out.append(f"- ⬆️ **{q}**: {d:+.0f} кликов")
        for q, d, a, b in down:
            out.append(f"- ⬇️ **{q}**: {d:+.0f} кликов")

    new = [(q, r) for q, r in q_now.items() if q not in q_old and r["impressions"] >= 5]
    if new:
        out.append("\n### Новые запросы (≥ 5 показов, на прошлой неделе не было)\n")
        for q, r in sorted(new, key=lambda kv: -kv[1]["impressions"])[:10]:
            out.append(f"- {q}: {r['impressions']:.0f} показов, позиция {fmt_pos(r['position'])}")

    wins = [(q, r) for q, r in q_now.items() if 5 <= r["position"] <= 15 and r["impressions"] >= 10]
    if wins:
        out.append("\n### Быстрые победы (позиции 5–15, много показов)\n")
        for q, r in sorted(wins, key=lambda kv: -kv[1]["impressions"])[:8]:
            out.append(f"- {q}: {r['impressions']:.0f} показов, позиция {fmt_pos(r['position'])}")

    for label, words in SEASON_WORDS.items():
        rows = [(q, r) for q, r in q_now.items() if any(w in q.lower() for w in words)]
        out.append(f"\n### Сезон: {label}\n")
        if not rows:
            out.append("- Запросов с показами нет.")
        for q, r in sorted(rows, key=lambda kv: -kv[1]["impressions"])[:8]:
            out.append(f"- {q}: {r['clicks']:.0f} кликов, {r['impressions']:.0f} показов, позиция {fmt_pos(r['position'])}")

    out.append("\n### Топ-10 страниц\n")
    out.append("| Страница | Клики | Показы | Позиция |\n|---|---|---|---|")
    for r in sorted(gsc(s, *cur, ["page"], 50), key=lambda r: -r["clicks"])[:10]:
        page = r["keys"][0].replace("https://marmarcandles.com", "").replace("https://www.marmarcandles.com", "www:") or "/"
        out.append(f"| {page} | {r['clicks']:.0f} | {r['impressions']:.0f} | {fmt_pos(r['position'])} |")
    return out


# ---------- Merchant Center ----------
def merchant_section(s):
    out = ["\n## Google Покупки (Merchant Center)\n"]
    products, token = [], None
    while True:
        params = {"pageSize": 250}
        if token:
            params["pageToken"] = token
        r = s.get(f"https://merchantapi.googleapis.com/products/v1/{MERCHANT}/products", params=params, timeout=60)
        if not r.ok:
            out.append(f"- Не удалось получить товары: HTTP {r.status_code}")
            return out
        d = r.json()
        products += d.get("products", [])
        token = d.get("nextPageToken")
        if not token:
            break

    by_source = collections.Counter(p.get("dataSource", "?").rsplit("/", 1)[-1] for p in products)
    status = collections.Counter()
    issues = collections.Counter()
    for p in products:
        ps = p.get("productStatus", {})
        if not any(d.get("reportingContext") == "FREE_LISTINGS" for d in ps.get("destinationStatuses", [])):
            status["ещё без статуса"] += 1
        for d in ps.get("destinationStatuses", []):
            if d.get("reportingContext") != "FREE_LISTINGS":
                continue
            if "LV" in d.get("approvedCountries", []):
                status["одобрено"] += 1
            elif "LV" in d.get("pendingCountries", []):
                status["на проверке"] += 1
            elif "LV" in d.get("disapprovedCountries", []):
                status["отклонено"] += 1
        for i in ps.get("itemLevelIssues", []):
            ctx = i.get("reportingContext", "")
            if ctx in ("", "FREE_LISTINGS", "SHOPPING_ADS"):
                issues[(i.get("severity"), i.get("description") or i.get("code"))] += 1

    out.append(f"- Всего товаров: **{len(products)}** (по источникам: " + ", ".join(f"{k}: {v}" for k, v in by_source.items()) + ")")
    out.append("- Бесплатные объявления в Латвии: " + (", ".join(f"{k}: **{v}**" for k, v in status.items()) or "нет данных"))
    if issues:
        out.append("\n**Проблемы с товарами:**\n")
        for (sev, desc), n in issues.most_common(8):
            out.append(f"- {n} × {desc} ({sev})")

    r = s.get(f"https://merchantapi.googleapis.com/datasources/v1/{FEED_SOURCE}/fileUploads/latest", timeout=60)
    if r.ok:
        u = r.json()
        out.append(
            f"\n- Последняя загрузка фида: {u.get('uploadTime', '?')[:16].replace('T', ' ')} UTC, "
            f"статус {u.get('processingState')}, товаров {u.get('itemsTotal')}, создано {u.get('itemsCreated', 0)}, "
            f"обновлено {u.get('itemsUpdated', 0)}"
        )
        for i in u.get("issues", [])[:5]:
            out.append(f"  - {i.get('title')}: {i.get('count')} шт.")
    return out


# ---------- GA4 ----------
def ga4(s, start, end):
    body = {
        "dateRanges": [{"startDate": str(start), "endDate": str(end)}],
        "dimensions": [{"name": "sessionDefaultChannelGroup"}],
        "metrics": [{"name": "sessions"}, {"name": "ecommercePurchases"}, {"name": "purchaseRevenue"}],
    }
    r = s.post(f"https://analyticsdata.googleapis.com/v1beta/{GA4_PROPERTY}:runReport", json=body, timeout=60)
    r.raise_for_status()
    rows = {}
    for row in r.json().get("rows", []):
        rows[row["dimensionValues"][0]["value"]] = [float(m["value"]) for m in row["metricValues"]]
    return rows


def ga4_section(s, cur, prev):
    out = ["\n## Сайт и покупки (Google Analytics 4)\n"]
    now, old = ga4(s, *cur), ga4(s, *prev)
    tn = [sum(v[i] for v in now.values()) for i in range(3)]
    to = [sum(v[i] for v in old.values()) for i in range(3)]
    out.append("| | Эта неделя | Прошлая | Изменение |\n|---|---|---|---|")
    out.append(f"| Визиты | {tn[0]:.0f} | {to[0]:.0f} | {pct(tn[0], to[0])} |")
    out.append(f"| Покупки | {tn[1]:.0f} | {to[1]:.0f} | {pct(tn[1], to[1])} |")
    out.append(f"| Выручка | {tn[2]:.2f} € | {to[2]:.2f} € | {pct(tn[2], to[2])} |\n")
    out.append("| Источник | Визиты | Покупки | Выручка |\n|---|---|---|---|")
    for ch, v in sorted(now.items(), key=lambda kv: -kv[1][0]):
        out.append(f"| {ch} | {v[0]:.0f} | {v[1]:.0f} | {v[2]:.2f} € |")
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--end", help="last day of the report week (default: 3 days ago, Search Console lag)")
    a = ap.parse_args()
    end = dt.date.fromisoformat(a.end) if a.end else dt.date.today() - dt.timedelta(days=3)
    cur = (end - dt.timedelta(days=6), end)
    prev = (end - dt.timedelta(days=13), end - dt.timedelta(days=7))

    s = session()
    lines = [
        f"# SEO-отчёт MarMar Candles: {cur[0]:%d.%m} – {cur[1]:%d.%m.%Y}",
        f"_Сравнение с неделей {prev[0]:%d.%m} – {prev[1]:%d.%m}. Данные Search Console приходят с задержкой ~3 дня._\n",
    ]
    for section in (lambda: gsc_section(s, cur, prev), lambda: merchant_section(s), lambda: ga4_section(s, cur, prev)):
        try:
            lines += section()
        except Exception as e:  # keep the rest of the report if one API fails
            lines.append(f"\n> ⚠️ Раздел не собран: {type(e).__name__}: {str(e)[:200]}")
    print("\n".join(lines))


if __name__ == "__main__":
    main()
