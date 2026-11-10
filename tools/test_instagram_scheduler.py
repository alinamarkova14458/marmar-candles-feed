"""Tests for instagram_scheduler.plan / problem. Run: python3 -m pytest tools -q"""
import os
import sys
from datetime import datetime

sys.path.insert(0, os.path.dirname(__file__))
import instagram_scheduler as ig  # noqa: E402

NOW = datetime(2026, 11, 20, 18, 0, tzinfo=ig.RIGA)
JPG = "https://raw.githubusercontent.com/x/y/master/social/instagram/media/1.jpg"


def post(date, **kw):
    it = {"date": date, "media": [JPG], "caption": "Sveiki! Es uztaisīju 🎂 #svecites"}
    it.update(kw)
    it["_when"] = ig.parse_date(it["date"])
    it["_file"] = "q.json"
    return it


def test_due_published_future_waits():
    pub, skip = ig.plan([post("2026-11-20T17:00:00"), post("2026-11-20T19:00:00")], {}, NOW)
    assert len(pub) == 1 and skip == []


def test_done_ignored_and_too_late_skipped():
    done = post("2026-11-20T17:00:00")
    state = {ig.post_id(done): {"media_id": "1"}}
    pub, skip = ig.plan([done, post("2026-11-19T12:00:00", media=[JPG + "?b"])], state, NOW)
    assert pub == [] and [w for _, w in skip] == ["too late"]


def test_brand_and_api_rules():
    assert "hashtags" in ig.problem(post("2026-11-20T17:00:00", caption="#a #b #c #d #e #f"))
    assert ig.problem(post("2026-11-20T17:00:00", caption="#a #b #c #d #e")) is None
    assert "2-20" in ig.problem(post("2026-11-20T17:00:00", type="carousel"))
    assert ig.problem(post("2026-11-20T17:00:00", type="carousel", media=[JPG, JPG])) is None
    assert "exactly 1" in ig.problem(post("2026-11-20T17:00:00", type="reel", media=[JPG, JPG]))
    assert "https" in ig.problem(post("2026-11-20T17:00:00", media=["http://a/1.jpg"]))
    assert "unknown" in ig.problem(post("2026-11-20T17:00:00", type="story"))


def test_single_url_string_ok_and_stable_id():
    a, b = post("2026-11-20T17:00:00", media=JPG), post("2026-11-20T17:00:00", media=[JPG])
    assert ig.problem(a) is None and ig.post_id(a) == ig.post_id(b)


def test_publish_call_sequence(monkeypatch):
    calls = []

    class R:
        status_code = 200

        def __init__(self, j):
            self.j = j

        def json(self):
            return self.j

    def fake(method, url, headers, params, timeout):
        path = url.split("/v27.0/")[1]
        calls.append((method, path, params))
        if path.endswith("/media"):
            return R({"id": f"c{len(calls)}"})
        if path.endswith("/media_publish"):
            return R({"id": "M1"})
        return R({"status_code": "FINISHED"})

    monkeypatch.setattr(ig.requests, "request", fake)
    api = ig.Api("tok")
    assert api.publish("U", post("2026-11-20T17:00:00", type="carousel", media=[JPG, JPG])) == "M1"
    paths = [(m, p) for m, p, _ in calls]
    assert paths == [("POST", "U/media"), ("POST", "U/media"), ("GET", "c1"), ("GET", "c2"),
                     ("POST", "U/media"), ("GET", "c5"), ("POST", "U/media_publish")]
    assert calls[4][2]["children"] == "c1,c2" and calls[-1][2] == {"creation_id": "c5"}


def test_account_falls_back_to_page(monkeypatch):
    class R:
        status_code = 200

        def __init__(self, j):
            self.j = j

        def json(self):
            return self.j

    answers = {"me/assigned_instagram_accounts": {"data": []},
               "me/accounts": {"data": [{"id": "P"}, {"id": "P2", "instagram_business_account":
                                                      {"id": "IG1", "username": "marmar.candles"}}]}}
    monkeypatch.setattr(ig.requests, "request",
                        lambda method, url, headers, params, timeout: R(answers[url.split("/v27.0/")[1]]))
    assert ig.Api("t").account() == {"id": "IG1", "username": "marmar.candles"}
