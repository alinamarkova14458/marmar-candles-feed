from conftest import SITE, get


def test_sitemap_en():
    r = get("/mm-sitemap-en.xml")
    assert r.status_code == 200
    assert "xml" in r.headers["Content-Type"]
    x = r.text
    for path in ("/en/product/ciparu-svece-5/", "/en/product-category/ciparu-sveces/", "/en/piegade/"):
        assert f"<loc>{SITE}{path}</loc>" in x, path
    assert "galda-svece" not in x
    assert x.count("<url>") == x.count('hreflang="lv"') == x.count('hreflang="en"')


def test_sitemap_disabled_lang():
    assert get("/mm-sitemap-lt.xml").status_code == 404


def test_sitemap_hidden_mode(option):
    option("mm_lang_public", False)
    assert get("/mm-sitemap-en.xml").status_code == 404
