import os
import re
import shutil

from conftest import DIR, get

PAGES = ("/", "/product-category/ciparu-sveces/", "/product/ciparu-svece-5/")
REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))


def normalize(html):
    html = re.sub(r'<link rel="alternate" hreflang=[^>]+>\n?', "", html)
    html = re.sub(r'<script>document\.cookie="mm_lang=[^<]*</script>\n?', "", html)
    html = re.sub(r'<li class="menu-item mm-switch-item">.*?</li>', "", html, flags=re.S)
    html = re.sub(r'(nonce["\']?\s*[:=]\s*["\']?|_wpnonce=|"nonce":")[0-9a-f]{10}', r"\1N", html)
    html = re.sub(r'[0-9a-f]{10}(?=["\'&])', "N", html)
    html = re.sub(r'ct-search-(results|status)-[0-9a-f]{3}', r'ct-search-\1-X', html)  # Blocksy random ids
    html = re.sub(r'<section class="related products[^"]*".*?</section>', "", html, flags=re.S)  # random order
    return html


def test_lv_html_unchanged(wpcli):
    with_plugin = {p: normalize(get(p).text) for p in PAGES}
    wpcli("plugin", "deactivate", "marmar-lang")
    try:
        without = {p: normalize(get(p).text) for p in PAGES}
    finally:
        wpcli("plugin", "activate", "marmar-lang")
    for p in PAGES:
        assert with_plugin[p] == without[p], p


def test_deactivate_clean(wpcli, ids):
    wpcli("plugin", "deactivate", "marmar-lang")
    try:
        assert get("/en/product/ciparu-svece-5/", allow_redirects=False).status_code == 404
        for p in PAGES:
            assert get(p).status_code == 200, p
        assert wpcli("eval", f'echo get_post_meta({ids["simple"]}, "_mm_tr_en_title", true);') == "Number candle “5”"
    finally:
        wpcli("plugin", "activate", "marmar-lang")


def test_coexists_with_ux_snippet():
    target = os.path.join(DIR, "wp", "wp-content", "mu-plugins", "marmar-ux.php")
    shutil.copy(os.path.join(REPO, "site", "snippets", "marmar-ux.php"), target)
    try:
        cat = get("/en/product-category/ciparu-sveces/").text
        titles = re.findall(r'woocommerce-loop-product__title[^>]*>(?:\s*<a[^>]*>)?\s*([^<]+)', cat)
        assert "Christmas ball candle" in titles, titles
        assert not [t for t in titles if "Ziemassvētku" in t], titles
        prod = get("/en/product/ciparu-svece-5/").text
        assert 'class="mm-info"' in prod
        assert "Number candle “5”" in prod
    finally:
        os.remove(target)
