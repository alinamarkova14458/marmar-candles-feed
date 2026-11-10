#!/usr/bin/env python3
"""Free Instagram scheduler for MarMar: publishes due posts via Instagram API (Instagram Login).

Queue: social/instagram/queue/*.json, each file is a JSON list of posts:
  {"date": "2026-11-20T18:00:00",          # Riga time if no offset given
   "type": "image" | "carousel" | "reel",   # default "image"
   "media": ["https://.../1.jpg", ...],     # public URLs: JPEG for images, MP4/MOV for a reel
   "caption": "...",
   "id": "..."}                             # optional; default = hash of date+media
A queue file reaches master only by Alina merging a pull request: the merge is her approval.

State: social/instagram/published.json  {id: {"media_id"|"error"|"skipped": ..., "at": ...},
                                          "_token_refreshed_at": iso}
Env: IG_ACCESS_TOKEN (secret, long-lived token, 60 days, refreshed here weekly)
     DRY_RUN=1 -> print what would be published, call nothing, change no state
     NEW_TOKEN_FILE -> where a refreshed token is written for the workflow to save as a secret
Never prints tokens.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import sys
import time
from datetime import datetime, timedelta, timezone

import requests

from pinterest_scheduler import RIGA, load_queue, load_state, mask, parse_date, save_state  # noqa: F401

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
QUEUE_GLOB = os.path.join(ROOT, "social", "instagram", "queue", "*.json")
STATE_PATH = os.path.join(ROOT, "social", "instagram", "published.json")
API = "https://graph.instagram.com/v27.0"

MAX_LATE = timedelta(hours=24)   # GitHub cron runs hours late; later than this -> skipped
MAX_HASHTAGS = 5                 # Alina's brand rule
MAX_CAPTION = 2200               # Instagram limit
REFRESH_EVERY = timedelta(days=7)


def post_id(it: dict) -> str:
    if it.get("id"):
        return str(it["id"])
    raw = it["date"] + "|" + "|".join(media(it))
    return hashlib.sha1(raw.encode()).hexdigest()[:16]


def media(it: dict) -> list[str]:
    m = it.get("media", [])
    return [m] if isinstance(m, str) else list(m)


def problem(it: dict) -> str | None:
    """Reasons a post must not go out as is (checked before any API call)."""
    kind, urls, cap = it.get("type", "image"), media(it), it.get("caption", "")
    if kind not in ("image", "carousel", "reel"):
        return f"unknown type {kind}"
    if not urls or not all(u.startswith("https://") for u in urls):
        return "media must be public https URLs"
    if kind == "carousel" and not 2 <= len(urls) <= 20:
        return "carousel needs 2-20 media"
    if kind != "carousel" and len(urls) != 1:
        return f"{kind} needs exactly 1 media"
    if len(re.findall(r"#\w", cap)) > MAX_HASHTAGS:
        return f"more than {MAX_HASHTAGS} hashtags"
    if len(cap) > MAX_CAPTION:
        return f"caption longer than {MAX_CAPTION}"
    return None


def plan(items: list[dict], state: dict, now: datetime):
    """Return (to_publish, to_skip) for this run."""
    to_publish, to_skip = [], []
    for it in items:
        it["_id"] = post_id(it)
        if it["_id"] in state or it["_when"] > now:
            continue
        why = "too late" if now - it["_when"] > MAX_LATE else problem(it)
        (to_skip.append((it, why)) if why else to_publish.append(it))
    return to_publish, to_skip


class Api:
    def __init__(self, token: str):
        self.h = {"Authorization": f"Bearer {token}"}

    def call(self, method: str, path: str, **params) -> dict:
        r = requests.request(method, f"{API}/{path}", headers=self.h, params=params, timeout=60)
        if r.status_code != 200:
            raise RuntimeError(f"{path}: HTTP {r.status_code} {r.text[:300]}")
        return r.json()

    def user_id(self) -> str:
        j = self.call("GET", "me", fields="user_id,username")
        j = j["data"][0] if "data" in j else j  # docs show both shapes
        return j["user_id"]

    def wait(self, container: str) -> None:
        for _ in range(10):  # reels need processing time; docs: poll ~1/min, max 5 min
            status = self.call("GET", container, fields="status_code")["status_code"]
            if status == "FINISHED":
                return
            if status in ("ERROR", "EXPIRED"):
                raise RuntimeError(f"container {status}")
            time.sleep(30)
        raise RuntimeError("container still processing after 5 min")

    def publish(self, uid: str, it: dict) -> str:
        kind, urls, cap = it.get("type", "image"), media(it), it.get("caption", "")
        if kind == "image":
            c = self.call("POST", f"{uid}/media", image_url=urls[0], caption=cap)["id"]
        elif kind == "reel":
            c = self.call("POST", f"{uid}/media", media_type="REELS", video_url=urls[0],
                          caption=cap, share_to_feed="true")["id"]
        else:
            kids = [self.call("POST", f"{uid}/media", image_url=u, is_carousel_item="true")["id"]
                    for u in urls]
            for k in kids:
                self.wait(k)
            c = self.call("POST", f"{uid}/media", media_type="CAROUSEL",
                          children=",".join(kids), caption=cap)["id"]
        self.wait(c)
        return self.call("POST", f"{uid}/media_publish", creation_id=c)["id"]


def refresh_token(token: str) -> str:
    r = requests.get("https://graph.instagram.com/refresh_access_token", timeout=30,
                     params={"grant_type": "ig_refresh_token", "access_token": token})
    if r.status_code != 200:
        raise RuntimeError(f"token refresh: HTTP {r.status_code}")  # body may echo the token
    return r.json()["access_token"]


def main() -> int:
    dry = os.environ.get("DRY_RUN") == "1"
    now = datetime.now(timezone.utc)
    state = load_state(STATE_PATH)
    to_publish, to_skip = plan(load_queue(QUEUE_GLOB), state, now)
    print(f"{now.astimezone(RIGA):%Y-%m-%d %H:%M} Riga | dry={dry} | "
          f"due={len(to_publish)} skip={len(to_skip)}")

    for it, why in to_skip:
        print(f"  SKIP {it['_id']} {it['date']} ({why})")
        if not dry:
            state[it["_id"]] = {"skipped": why, "at": now.isoformat(), "file": it["_file"]}
    if dry:
        for it in to_publish:
            print(f"  WOULD {it['_id']} {it['date']} {it.get('type', 'image')} {it.get('caption', '')[:60]!r}")
        return 0

    token = os.environ["IG_ACCESS_TOKEN"]
    mask(token)
    last = state.get("_token_refreshed_at")
    if not last or now - datetime.fromisoformat(last) > REFRESH_EVERY:
        try:
            token = refresh_token(token)
            mask(token)
            with open(os.environ["NEW_TOKEN_FILE"], "w") as f:
                f.write(token)
            state["_token_refreshed_at"] = now.isoformat()
            print("  token refreshed")
        except Exception as e:  # a failed refresh must not block publishing with the old token
            print(f"::warning::{e}")

    api = Api(token)
    uid = api.user_id() if to_publish else None
    for it in to_publish:
        rec = {"at": now.isoformat(), "file": it["_file"]}
        try:
            rec["media_id"] = api.publish(uid, it)
            print(f"  OK   {it['_id']} -> {rec['media_id']}")
        except Exception as e:
            rec["error"] = str(e)
            print(f"  FAIL {it['_id']} {e}")
        state[it["_id"]] = rec  # failures are recorded, never retried blindly (no double posts)
    save_state(state, STATE_PATH)
    return 1 if any("error" in state[it["_id"]] for it in to_publish) else 0


if __name__ == "__main__":
    sys.exit(main())
