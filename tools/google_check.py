#!/usr/bin/env python3
"""Check read-only access to Google Search Console, GA4 and Merchant Center.

Reads the service-account key from the GOOGLE_SERVICE_ACCOUNT_JSON environment
variable. Never prints the key itself.

Usage:
    pip install -q google-auth requests
    python3 tools/google_check.py
"""
import json
import os
import sys

SCOPES = [
    "https://www.googleapis.com/auth/webmasters.readonly",
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/content",
]

CHECKS = [
    ("Search Console", "https://searchconsole.googleapis.com/webmasters/v3/sites"),
    ("Google Analytics 4", "https://analyticsadmin.googleapis.com/v1beta/accountSummaries"),
    ("Merchant Center", "https://merchantapi.googleapis.com/accounts/v1/accounts"),
]


def main() -> int:
    raw = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not raw:
        print("FAIL: GOOGLE_SERVICE_ACCOUNT_JSON is not set in this session.")
        return 1
    try:
        info = json.loads(raw)
    except json.JSONDecodeError as e:
        print(f"FAIL: GOOGLE_SERVICE_ACCOUNT_JSON is not valid JSON ({e.msg}, length {len(raw)}).")
        return 1
    print(f"Key OK: {info.get('client_email')} (project {info.get('project_id')})")

    from google.oauth2 import service_account
    from google.auth.transport.requests import AuthorizedSession

    creds = service_account.Credentials.from_service_account_info(info, scopes=SCOPES)
    session = AuthorizedSession(creds)

    ok = True
    for name, url in CHECKS:
        try:
            r = session.get(url, timeout=30)
        except Exception as e:  # network / auth errors
            print(f"[FAIL] {name}: {type(e).__name__}: {e}")
            ok = False
            continue
        if r.status_code == 200:
            data = r.json()
            items = (
                data.get("siteEntry")
                or data.get("accountSummaries")
                or data.get("accounts")
                or []
            )
            print(f"[ OK ] {name}: {len(items)} item(s)")
            for it in items:
                label = it.get("siteUrl") or it.get("displayName") or it.get("accountName") or it.get("name")
                extra = ""
                if "propertySummaries" in it:
                    extra = " | properties: " + ", ".join(
                        f"{p.get('displayName')} ({p.get('property')})" for p in it["propertySummaries"]
                    )
                print(f"       - {label} [{it.get('permissionLevel') or it.get('name') or it.get('account', '')}]{extra}")
            if not items:
                ok = False
                print("       (empty: the service account has not been added as a user here)")
        else:
            ok = False
            msg = r.text[:300].replace("\n", " ")
            print(f"[FAIL] {name}: HTTP {r.status_code}: {msg}")
    return 0 if ok else 2


if __name__ == "__main__":
    sys.exit(main())
