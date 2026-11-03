import re

from conftest import SITE, get


def h1(html):
    m = re.search(r"<h1[^>]*>(.*?)</h1>", html, re.S)
    return re.sub(r"<[^>]+>", "", m.group(1)).strip() if m else ""


def test_product_translated():
    html = get("/en/product/ciparu-svece-5/").text
    assert h1(html) == "Number candle “5”"
    assert "English description of the number candle." in html
    assert "Short English description." in html
    assert "Latviešu apraksts ciparu svecei." not in html
    assert "Īss latviešu apraksts." not in html


def test_category_and_cards_translated():
    html = get("/en/product-category/ciparu-sveces/").text
    assert h1(html) == "Number candles"
    assert "English category description." in html
    assert "Number candle “5”" in html
    assert "Christmas ball candle" in html


def test_menu_translated():
    html = get("/en/product-category/ciparu-sveces/").text
    menu = re.search(r'<ul[^>]+class="menu"[^>]*>(.*?)</ul>', html, re.S).group(1)
    assert ">Delivery<" in menu
    assert ">Sākums<" in menu  # no translation → stays Latvian


def test_attribute_terms_translated():
    html = get("/en/product/ziemassvetku-bumba/").text
    assert "Burgundy" in html
    assert 'title="Bordo"' not in html


def test_missing_translation_redirect():
    r = get("/en/product/galda-svece/", allow_redirects=False)
    assert r.status_code == 302
    assert r.headers["Location"] == SITE + "/product/galda-svece/"


def test_partial_translation_falls_back():
    r = get("/en/product/sirds-svece/")
    assert r.status_code == 200
    assert h1(r.text) == "Heart candle"
    assert "Sirds īsais apraksts." in r.text


def test_lv_untouched():
    html = get("/product/ciparu-svece-5/").text
    assert h1(html) == "Ciparu svece “5”"
    assert "Latviešu apraksts ciparu svecei." in html
    assert "English description" not in html
    cat = get("/product-category/ciparu-sveces/").text
    assert h1(cat) == "Ciparu sveces"


def test_rest_editor_untouched(rest, ids):
    """Admin REST (block editor) must stay Latvian even with the mm_lang=en cookie."""
    r = rest("GET", f"wp/v2/product/{ids['simple']}", params={"context": "edit"},
             cookies={"mm_lang": "en"})
    assert r.json()["title"]["raw"] == "Ciparu svece “5”"
    assert "/en/" not in r.json()["link"]
