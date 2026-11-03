import re

from conftest import SITE, get

P = "/product/ciparu-svece-5/"


def alternates(html):
    return dict(re.findall(r'<link rel="alternate" hreflang="([^"]+)" href="([^"]+)"', html))


def canonicals(html):
    return re.findall(r'<link rel="canonical" href="([^"]+)"', html)


def test_hreflang_pairs():
    for path in (P, "/en" + P):
        alt = alternates(get(path).text)
        assert alt.get("lv") == SITE + P, alt
        assert alt.get("en") == SITE + "/en" + P, alt
        assert alt.get("x-default") == SITE + P, alt


def test_hreflang_skips_missing():
    alt = alternates(get("/product/galda-svece/").text)
    assert "en" not in alt
    assert alt.get("lv") == SITE + "/product/galda-svece/"


def test_canonical_self():
    assert canonicals(get("/en" + P).text) == [SITE + "/en" + P]
    assert canonicals(get(P).text) == [SITE + P]


def test_rank_math_meta():
    html = get("/en" + P).text
    assert "<title>Number candle 5 for cakes | MarMar Candles</title>" in html
    assert '<meta name="description" content="Handmade number candle 5 for birthday cakes."' in html
    html = get("/en/product/sirds-svece/").text
    title = re.search(r"<title>(.*?)</title>", html).group(1)
    assert "Heart candle" in title and "Sirds svece" not in title


def test_og_locale():
    html = get("/en" + P).text
    assert '<meta property="og:locale" content="en_GB"' in html


def test_hidden_mode_no_hreflang(option):
    option("mm_lang_public", False)
    assert "hreflang" not in get(P).text
