from conftest import get


def test_site_up():
    r = get("/product/ciparu-svece-5/")
    assert r.status_code == 200
    assert "Ciparu svece" in r.text


def test_meta_rest_roundtrip(rest, ids):
    pid = ids["simple"]
    r = rest("POST", f"wp/v2/product/{pid}", json={"meta": {"_mm_tr_en_title": "Number candle “5”"}})
    assert r.status_code == 200, r.text
    r = rest("GET", f"wp/v2/product/{pid}", params={"context": "edit"})
    assert r.json()["meta"]["_mm_tr_en_title"] == "Number candle “5”"


def test_term_meta_rest(rest, ids):
    r = rest("GET", f"wp/v2/product_cat/{ids['cat']}", params={"context": "edit"})
    assert r.json()["meta"]["_mm_tr_en_name"] == "Number candles"


def test_meta_requires_auth(rest, ids):
    r = rest("POST", f"wp/v2/product/{ids['simple']}", auth=False,
             json={"meta": {"_mm_tr_en_title": "hack"}})
    assert r.status_code == 401


def test_tr_has(wpcli, ids):
    assert wpcli("eval", f'var_export(mm_tr_has("post", {ids["simple"]}, "en"));') == "true"
    assert wpcli("eval", f'var_export(mm_tr_has("post", {ids["untranslated"]}, "en"));') == "false"
    assert wpcli("eval", f'var_export(mm_tr_has("term", {ids["cat"]}, "en"));') == "true"
