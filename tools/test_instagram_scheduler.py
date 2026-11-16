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
    assert "unknown" in ig.problem(post("2026-11-20T17:00:00", type="live"))
    assert ig.problem(post("2026-11-20T17:00:00", type="story", caption="")) is None
    assert "exactly 1" in ig.problem(post("2026-11-20T17:00:00", type="story", media=[JPG, JPG]))


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


def test_plan_page():
    import instagram_plan as plan
    up = post("2026-11-21T18:00:00", type="carousel", media=[JPG] * 5, caption="Ko saka | klienti")
    done = post("2026-11-12T18:00:00")
    state = {ig.post_id(done): {"media_id": "1", "permalink": "https://instagram.com/p/x"}}
    prs = [{"title": "Пост", "url": "https://github.com/x/pull/1", "files": [{"path": "social/instagram/queue/a.json"}]},
           {"title": "Код", "url": "u", "files": [{"path": "tools/x.py"}]}]
    md = plan.render([up, done], state, prs, NOW)
    assert "[Пост](https://github.com/x/pull/1)" in md and "Код" not in md
    assert "сб 21.11 18:00" in md and "карусель (5)" in md and "+2" in md and "Ko saka / klienti" in md
    assert "[опубликован](https://instagram.com/p/x)" in md and "чт 12.11 18:00" in md


def test_story_image_and_video(monkeypatch):
    calls = []

    class R:
        status_code = 200

        def __init__(self, j):
            self.j = j

        def json(self):
            return self.j

    def fake(method, url, headers, params, timeout):
        path = url.split("/v27.0/")[1]
        calls.append((path, params))
        if path.endswith("/media"):
            return R({"id": "c"})
        if path.endswith("/media_publish"):
            return R({"id": "S1"})
        return R({"status_code": "FINISHED"})

    monkeypatch.setattr(ig.requests, "request", fake)
    api = ig.Api("tok")
    assert api.publish("U", post("2026-11-20T17:00:00", type="story", caption="")) == "S1"
    assert calls[0] == ("U/media", {"media_type": "STORIES", "image_url": JPG})
    calls.clear()
    api.publish("U", post("2026-11-20T17:00:00", type="story", media=["https://x/v.MP4"]))
    assert calls[0] == ("U/media", {"media_type": "STORIES", "video_url": "https://x/v.MP4"})


def test_next_ahead():
    soon, later = post("2026-11-20T21:00:00"), post("2026-11-21T09:00:00", media=[JPG + "?x"])
    assert ig.next_ahead([later, soon], {}, NOW) == ig.parse_date("2026-11-20T21:00:00") - NOW
    assert ig.next_ahead([soon], {ig.post_id(soon): {"media_id": "1"}}, NOW) is None
    assert ig.next_ahead([post("2026-11-20T17:00:00")], {}, NOW) is None  # due already, not ahead


def _duty(monkeypatch, tmp_path, posts, publish_at=None):
    """Run main() on a fake clock; returns (runs, chain file exists, elapsed)."""
    clock = {"t": NOW}
    state, runs = {}, []

    def fake_run(dry):
        runs.append(clock["t"])
        for it in posts:
            if it["_when"] <= clock["t"]:
                state[ig.post_id(it)] = {"media_id": "1"}
        return False

    class D:
        @staticmethod
        def now(tz=None):
            return clock["t"]

    def sleep(sec):
        clock["t"] += ig.timedelta(seconds=sec)

    chain = tmp_path / "chain"
    monkeypatch.setenv("CHAIN_FILE", str(chain))
    monkeypatch.delenv("DRY_RUN", raising=False)
    monkeypatch.setattr(ig, "run_once", fake_run)
    monkeypatch.setattr(ig, "load_queue", lambda _g: [dict(p) for p in posts])
    monkeypatch.setattr(ig, "load_state", lambda _p: state)
    monkeypatch.setattr(ig, "datetime", D)
    monkeypatch.setattr(ig.time, "sleep", sleep)
    assert ig.main() == 0
    return runs, chain.exists(), clock["t"] - NOW, state


def test_duty_publishes_on_time_then_stops(monkeypatch, tmp_path):
    p = post("2026-11-20T18:40:00")  # 40 min ahead
    runs, chained, elapsed, state = _duty(monkeypatch, tmp_path, [p])
    assert ig.post_id(p) in state and not chained
    assert ig.timedelta(minutes=40) <= elapsed <= ig.timedelta(minutes=41)  # published right at its time


def test_duty_hands_over_when_next_post_is_far(monkeypatch, tmp_path):
    p = post("2026-11-21T12:00:00")  # 18 h ahead, inside the 48 h horizon
    runs, chained, elapsed, state = _duty(monkeypatch, tmp_path, [p])
    assert chained and not state and elapsed == ig.WAIT_AHEAD


def test_no_duty_without_posts_soon(monkeypatch, tmp_path):
    runs, chained, elapsed, _ = _duty(monkeypatch, tmp_path, [post("2026-11-25T12:00:00")])
    assert len(runs) == 1 and not chained and elapsed == ig.timedelta(0)
