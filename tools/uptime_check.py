#!/usr/bin/env python3
"""Uptime monitor for marmarcandles.com (runs in GitHub Actions).

Checks a few key pages. A page counts as down only if it fails twice in a row
(60 s apart), so one slow reply does not raise an alarm.

On outage: opens (or comments on) a GitHub issue labelled "site-down", so
GitHub emails the repo owner. On recovery: comments and closes the issue.

Needs env GITHUB_TOKEN and GITHUB_REPOSITORY (set by Actions).
"""
import json
import os
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone

PAGES = [
    "https://marmarcandles.com/lv/",
    "https://marmarcandles.com/lv/product/dabiga-vaska-kuku-svecites-8-cm/",
    "https://marmarcandles.com/lv/cart/",
]
UA = "Mozilla/5.0 (compatible; MarMarUptime/1.0)"
LABEL = "site-down"
TITLE = "⚠️ marmarcandles.com nedarbojas"


def probe(url):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    t = time.time()
    try:
        with urllib.request.urlopen(req, timeout=45) as r:
            return r.status, round(time.time() - t, 1)
    except urllib.error.HTTPError as e:
        return e.code, round(time.time() - t, 1)
    except Exception as e:  # timeout, DNS, TLS, connection reset
        return f"{type(e).__name__}", round(time.time() - t, 1)


def check():
    first = {u: probe(u) for u in PAGES}
    bad = [u for u, (s, _) in first.items() if s != 200]
    if not bad:
        return first, []
    time.sleep(60)
    second = {u: probe(u) for u in bad}
    still = [u for u in bad if second[u][0] != 200]
    first.update(second)
    return first, still


def gh(method, path, body=None):
    url = f"https://api.github.com/repos/{os.environ['GITHUB_REPOSITORY']}{path}"
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method, headers={
        "Authorization": f"Bearer {os.environ['GITHUB_TOKEN']}",
        "Accept": "application/vnd.github+json",
        "User-Agent": UA,
    })
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read() or b"null")


def main():
    results, down = check()
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")
    table = "\n".join(f"| {u} | {s} | {t} s |" for u, (s, t) in results.items())
    report = f"Pārbaude {now}\n\n| Lapa | Statuss | Laiks |\n|---|---|---|\n{table}"
    print(report)

    if not os.environ.get("GITHUB_TOKEN"):
        return 1 if down else 0

    open_issues = gh("GET", "/issues?state=open&per_page=100")
    issue = next((i for i in open_issues if i["title"].startswith(TITLE)), None)

    if down:
        if issue:
            # remind at most once an hour, not every 5 minutes
            upd = datetime.strptime(issue["updated_at"], "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)
            if (datetime.now(timezone.utc) - upd).total_seconds() > 3600:
                gh("POST", f"/issues/{issue['number']}/comments", {"body": "Joprojām nedarbojas.\n\n" + report})
        else:
            gh("POST", "/issues", {
                "title": f"{TITLE} ({now})",
                "labels": [LABEL],
                "body": "Vietne neatbild divas reizes pēc kārtas (ar 60 s starplaiku).\n\n" + report
                        + "\n\nKad vietne atkal darbosies, šis paziņojums aizvērsies automātiski.",
            })
        return 1
    if issue:
        gh("POST", f"/issues/{issue['number']}/comments", {"body": "✅ Vietne atkal darbojas.\n\n" + report})
        gh("PATCH", f"/issues/{issue['number']}", {"state": "closed"})
    return 0


if __name__ == "__main__":
    sys.exit(main())
