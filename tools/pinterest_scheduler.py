#!/usr/bin/env python3
"""Free Pinterest scheduler: publishes due pins from a queue via Pinterest API v5.

Queue: social/pinterest/queue/*.json, each file is a JSON list of pins:
  {"date": "2026-11-20T16:00:00",        # Riga time if no offset given
   "board": "679128887499302730",        # board id
   "title": "...", "text": "...",         # pin title / description
   "media": "https://jalina.art/pins/x.jpg",  # public image URL
   "link": "https://jalina.art/...",      # destination URL
   "alt": "...",                          # optional alt text
   "id": "..."}                           # optional; default = hash of date+media+link

State: social/pinterest/published.json  {id: {"pin_id"|"error"|"skipped": ..., "at": ...}}

Env:
  PINTEREST_APP_ID, PINTEREST_APP_SECRET, PINTEREST_REFRESH_TOKEN  (secrets)
  PINTEREST_ENV = production | sandbox   (sandbox = Trial access; pins visible only to you)
  DRY_RUN = 1   -> print what would be published, call nothing, change no state
Never prints tokens.
"""
from __future__ import annotations

import base64
import glob
import hashlib
import json
import os
import sys
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo

import requests

RIGA = ZoneInfo("Europe/Riga")
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
QUEUE_GLOB = os.path.join(ROOT, "social", "pinterest", "queue", "*.json")
STATE_PATH = os.path.join(ROOT, "social", "pinterest", "published.json")

DAILY_CAP = 15          # Alina's rule: max 15 pins per day (Riga date), all sources
PER_RUN_CAP = 3         # spread catch-up after an outage instead of a burst
MAX_LATE = timedelta(hours=6)  # older than this -> skipped, not published

API = {"production": "https://api.pinterest.com/v5",
       "sandbox": "https://api-sandbox.pinterest.com/v5"}


def pin_id(item: dict) -> str:
    if item.get("id"):
        return str(item["id"])
    raw = f'{item["date"]}|{item["media"]}|{item["link"]}'
    return hashlib.sha1(raw.encode()).hexdigest()[:16]


def parse_date(s: str) -> datetime:
    d = datetime.fromisoformat(s)
    return d if d.tzinfo else d.replace(tzinfo=RIGA)


def load_queue(pattern: str = QUEUE_GLOB) -> list[dict]:
    items = []
    for path in sorted(glob.glob(pattern)):
        with open(path, encoding="utf-8") as f:
            for it in json.load(f):
                it = dict(it)
                it["_id"] = pin_id(it)
                it["_when"] = parse_date(it["date"])
                it["_file"] = os.path.basename(path)
                items.append(it)
    items.sort(key=lambda x: x["_when"])
    return items


def load_state(path: str = STATE_PATH) -> dict:
    if not os.path.exists(path):
        return {}
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def save_state(state: dict, path: str = STATE_PATH) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(state, f, ensure_ascii=False, indent=1, sort_keys=True)
        f.write("\n")


def published_on(state: dict, day) -> int:
    n = 0
    for rec in state.values():
        if rec.get("pin_id") and rec.get("at"):
            if datetime.fromisoformat(rec["at"]).astimezone(RIGA).date() == day:
                n += 1
    return n


def plan(items: list[dict], state: dict, now: datetime):
    """Return (to_publish, to_skip) for this run."""
    to_publish, to_skip = [], []
    used_today = published_on(state, now.astimezone(RIGA).date())
    for it in items:
        if it["_id"] in state or it["_when"] > now:
            continue
        if now - it["_when"] > MAX_LATE:
            to_skip.append((it, "too late"))
            continue
        if used_today + len(to_publish) >= DAILY_CAP:
            to_skip.append((it, f"daily cap {DAILY_CAP}"))
            continue
        if len(to_publish) >= PER_RUN_CAP:
            continue  # stays in queue for the next run
        to_publish.append(it)
    return to_publish, to_skip


def pin_payload(it: dict) -> dict:
    p = {"board_id": str(it["board"]),
         "title": it.get("title", "")[:100],
         "description": it.get("text", "")[:500],
         "link": it["link"],
         "media_source": {"source_type": "image_url", "url": it["media"]}}
    alt = it.get("alt") or it.get("title")
    if alt:
        p["alt_text"] = alt[:500]
    return p


def access_token(env: str) -> tuple[str, str | None]:
    """Refresh grant. Returns (access_token, new_refresh_token_or_None)."""
    cid, sec = os.environ["PINTEREST_APP_ID"], os.environ["PINTEREST_APP_SECRET"]
    rt = os.environ["PINTEREST_REFRESH_TOKEN"]
    basic = base64.b64encode(f"{cid}:{sec}".encode()).decode()
    r = requests.post(f"{API[env]}/oauth/token", timeout=30,  # sandbox has its own tokens
                      headers={"Authorization": f"Basic {basic}"},
                      data={"grant_type": "refresh_token", "refresh_token": rt})
    if r.status_code != 200:
        sys.exit(f"token refresh failed: HTTP {r.status_code}")  # body may echo secrets
    j = r.json()
    new_rt = j.get("refresh_token")
    return j["access_token"], (new_rt if new_rt and new_rt != rt else None)


SANDBOX_BOARD = "Sandbox test"


# Secret board "Sandbox test" created 08.11.2026 in the sandbox of account jalinaart.
# The sandbox GET /boards returns an empty list even for existing boards, and the
# OAuth sandbox token may not create boards (403), so use this id directly.
SANDBOX_BOARD_ID = os.environ.get("PINTEREST_SANDBOX_BOARD") or "679128887499450390"


def sandbox_board_id(token: str) -> str:
    """Trial access: pins go to the sandbox, which has its own boards."""
    if SANDBOX_BOARD_ID:
        return SANDBOX_BOARD_ID
    h = {"Authorization": f"Bearer {token}"}
    r = requests.get(f"{API['sandbox']}/boards", headers=h, timeout=30, params={"page_size": 100})
    if r.status_code == 200:
        for b in r.json().get("items", []):
            if b.get("name") == SANDBOX_BOARD:
                return b["id"]
    r = requests.post(f"{API['sandbox']}/boards", headers=h, timeout=30,
                      json={"name": SANDBOX_BOARD, "privacy": "SECRET"})
    if r.status_code not in (200, 201):
        sys.exit(f"sandbox board create failed: HTTP {r.status_code} {r.text[:200]}")
    print(f"  created sandbox board {r.json()['id']}")
    return r.json()["id"]


def mask(value: str) -> None:
    if os.environ.get("GITHUB_ACTIONS"):
        print(f"::add-mask::{value}")


def main() -> int:
    dry = os.environ.get("DRY_RUN") == "1"
    env = os.environ.get("PINTEREST_ENV", "sandbox")
    now = datetime.now(timezone.utc)
    state = load_state()
    to_publish, to_skip = plan(load_queue(), state, now)
    print(f"{now.astimezone(RIGA):%Y-%m-%d %H:%M} Riga | env={env} dry={dry} | "
          f"due={len(to_publish)} skip={len(to_skip)}")

    for it, why in to_skip:
        print(f"  SKIP {it['_id']} {it['date']} ({why}) {it.get('title','')[:60]}")
        if not dry:
            state[it["_id"]] = {"skipped": why, "at": now.isoformat(), "file": it["_file"]}

    if to_publish and not dry:
        token, new_rt = access_token(env)
        mask(token)
        if new_rt and os.environ.get("NEW_RT_FILE"):
            mask(new_rt)
            # rotated refresh token -> file for the workflow's "save secret" step (never logged)
            with open(os.environ["NEW_RT_FILE"], "w") as f:
                f.write(new_rt)
        sandbox_board = sandbox_board_id(token) if env == "sandbox" else None
        for it in to_publish:
            payload = pin_payload(it)
            if sandbox_board:  # real boards don't exist in the sandbox
                payload["board_id"] = sandbox_board
            r = requests.post(f"{API[env]}/pins", json=payload, timeout=60,
                              headers={"Authorization": f"Bearer {token}"})
            rec = {"at": now.isoformat(), "file": it["_file"], "env": env}
            if r.status_code in (200, 201):
                rec["pin_id"] = r.json().get("id")
                print(f"  OK   {it['_id']} -> pin {rec['pin_id']} {it.get('title','')[:60]}")
            else:
                msg = r.text[:300].replace("\n", " ")
                rec["error"] = f"HTTP {r.status_code}: {msg}"
                print(f"  FAIL {it['_id']} {rec['error']}")
                if r.status_code in (429, 500, 502, 503):
                    print("  transient error, will retry next run")
                    continue  # not recorded -> retried
            state[it["_id"]] = rec
    elif dry:
        for it in to_publish:
            print(f"  WOULD {it['_id']} {it['date']} board={it['board']} {it.get('title','')[:60]}")

    if not dry:
        save_state(state)
    return 0


if __name__ == "__main__":
    sys.exit(main())
