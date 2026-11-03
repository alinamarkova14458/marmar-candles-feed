import re

from conftest import SITE, get

SERVICE = re.compile(r"/(wp-content|wp-json|wp-admin|wp-includes)/|/wp-login\.php|wc-ajax=|/feed/")


def site_urls(html):
    html = re.sub(r'<link rel="alternate" hreflang=[^>]+>', "", html)  # hreflang points to other languages by design
    html = re.sub(r'<span class="mm-switch">.*?</span>', "", html, flags=re.S)  # so does the language switcher
    urls = re.findall(r'(?:href|src|action)=["\'](%s[^"\']*)["\']' % re.escape(SITE), html)
    return [u[len(SITE):] for u in urls]


def test_links_keep_language():
    html = get("/en/product-category/ciparu-sveces/?mm_pp=2").text
    paths = site_urls(html)
    content = [p for p in paths if re.match(r"(/en)?/(product|product-category|piegade)/", p) and "/feed/" not in p]
    assert content, "no content links found"
    assert all(p.startswith("/en/") for p in content), [p for p in content if not p.startswith("/en/")]
    pages = [p for p in paths if "/page/2/" in p]
    assert pages and all(p.startswith("/en/") for p in pages), pages
    home = [p for p in paths if p in ("/", "/en/")]
    assert home and all(p == "/en/" for p in home), home


def test_service_paths_untouched():
    html = get("/en/product-category/ciparu-sveces/").text
    bad = [p for p in site_urls(html) if SERVICE.search(p) and p.startswith("/en/")]
    assert not bad, bad
    assert "/en/wp-json" not in html.replace("\\/", "/")
    assert "/en/?wc-ajax" not in html.replace("\\/", "/")


def test_mm_lang_url(wpcli):
    def u(url, lang):
        return wpcli("eval", f'echo mm_lang_url("{url}", "{lang}");')
    assert u(SITE + "/product/x/?a=1", "en") == SITE + "/en/product/x/?a=1"
    assert u(SITE + "/en/product/x/", "lv") == SITE + "/product/x/"
    assert u(SITE + "/en/product/x/", "en") == SITE + "/en/product/x/"
    assert u(SITE + "/", "en") == SITE + "/en/"
    assert u("https://example.com/x", "en") == "https://example.com/x"
    assert u(SITE + "/wp-json/wc/store/v1/cart", "en") == SITE + "/wp-json/wc/store/v1/cart"


def test_lv_links_unprefixed():
    html = get("/product-category/ciparu-sveces/?mm_pp=2").text
    assert not [p for p in site_urls(html) if p.startswith("/en/")]
