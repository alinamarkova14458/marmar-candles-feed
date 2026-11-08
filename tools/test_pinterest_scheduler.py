"""Tests for pinterest_scheduler.plan / helpers. Run: python3 -m pytest tools -q"""
import os
import sys
from datetime import datetime, timedelta

sys.path.insert(0, os.path.dirname(__file__))
import pinterest_scheduler as ps  # noqa: E402

RIGA = ps.RIGA


def item(date, n=0, **kw):
    it = {"date": date, "board": "1", "title": f"t{n}", "text": "x",
          "media": f"https://jalina.art/pins/{n}.jpg", "link": f"https://jalina.art/{n}/"}
    it.update(kw)
    it["_id"] = ps.pin_id(it)
    it["_when"] = ps.parse_date(it["date"])
    it["_file"] = "q.json"
    return it


NOW = datetime(2026, 11, 20, 18, 0, tzinfo=RIGA)


def test_naive_date_is_riga():
    assert ps.parse_date("2026-11-20T16:00:00").utcoffset() == timedelta(hours=2)


def test_future_not_published_due_published():
    items = [item("2026-11-20T17:40:00", 1), item("2026-11-20T18:20:00", 2)]
    pub, skip = ps.plan(items, {}, NOW)
    assert [i["title"] for i in pub] == ["t1"] and skip == []


def test_already_done_ignored():
    it = item("2026-11-20T17:40:00", 1)
    pub, _ = ps.plan([it], {it["_id"]: {"pin_id": "9", "at": NOW.isoformat()}}, NOW)
    assert pub == []


def test_too_late_skipped():
    pub, skip = ps.plan([item("2026-11-20T08:00:00", 1)], {}, NOW)
    assert pub == [] and skip[0][1] == "too late"


def test_per_run_cap_leaves_rest_queued():
    items = [item(f"2026-11-20T17:{m:02d}:00", m) for m in range(10, 16)]
    pub, skip = ps.plan(items, {}, NOW)
    assert len(pub) == ps.PER_RUN_CAP and skip == []


def test_daily_cap_counts_already_published_today():
    state = {f"x{i}": {"pin_id": str(i), "at": NOW.replace(hour=15).isoformat()}
             for i in range(ps.DAILY_CAP)}
    pub, skip = ps.plan([item("2026-11-20T17:50:00", 1)], state, NOW)
    assert pub == [] and "daily cap" in skip[0][1]


def test_payload_limits_and_fields():
    p = ps.pin_payload(item("2026-11-20T17:50:00", 1, title="T" * 150, text="D" * 900))
    assert len(p["title"]) == 100 and len(p["description"]) == 500
    assert p["media_source"] == {"source_type": "image_url", "url": "https://jalina.art/pins/1.jpg"}
    assert p["board_id"] == "1" and p["alt_text"]


def test_explicit_id_wins():
    assert ps.pin_id({"id": "abc", "date": "d", "media": "m", "link": "l"}) == "abc"
