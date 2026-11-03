import requests

from conftest import SITE, get


def admin_session():
    s = requests.Session()
    s.get(SITE + "/wp-login.php", timeout=60)
    s.post(SITE + "/wp-login.php", data={"log": "admin", "pwd": "admin", "wp-submit": "Log In",
                                         "testcookie": "1"}, timeout=60)
    return s


def test_en_prefix_serves_page():
    r = get("/en/product/ciparu-svece-5/", allow_redirects=False)
    assert r.status_code == 200
    assert '<html lang="en-GB"' in r.text


def test_lv_has_no_prefix():
    r = get("/product/ciparu-svece-5/", allow_redirects=False)
    assert r.status_code == 200
    assert '<html lang="lv-LV"' in r.text


def test_legacy_lv_redirect():
    r = get("/lv/product/ciparu-svece-5/?a=1", allow_redirects=False)
    assert r.status_code == 301
    assert r.headers["Location"] == SITE + "/product/ciparu-svece-5/?a=1"
    r = get("/lv/", allow_redirects=False)
    assert r.status_code == 301
    assert r.headers["Location"] == SITE + "/"


def test_disabled_language_404():
    r = get("/lt/product/ciparu-svece-5/", allow_redirects=False)
    assert r.status_code == 404


def test_hidden_mode(option):
    option("mm_lang_public", False)
    r = get("/en/product/ciparu-svece-5/", allow_redirects=False)
    assert r.status_code == 302
    assert r.headers["Location"] == SITE + "/product/ciparu-svece-5/"
    r = admin_session().get(SITE + "/en/product/ciparu-svece-5/", allow_redirects=False, timeout=60)
    assert r.status_code == 200


def test_cookie_set():
    r = get("/en/product/ciparu-svece-5/", allow_redirects=False)
    assert "mm_lang=en" in r.headers.get("Set-Cookie", "")
    r = get("/product/ciparu-svece-5/", allow_redirects=False)
    assert "mm_lang=lv" in r.headers.get("Set-Cookie", "")
