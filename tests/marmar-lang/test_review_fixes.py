"""Regression tests for findings of the final whole-branch review."""
import json
import re

import requests

from conftest import SITE, get
from test_woo import add_item, checkout, mails, shopper


def test_bulk_emails_each_in_order_language(ids, wpcli):
    """Important 1: several customer emails in one request keep their own order languages."""
    order_ids = []
    for path in ("/en/product/ciparu-svece-5/", "/en/product/ciparu-svece-5/", "/product/ciparu-svece-5/"):
        s = shopper(path)
        add_item(s, ids["simple"])
        order_ids.append(checkout(s))
    before = len(mails())
    wpcli("eval", "foreach (%s as $id) { wc_get_order($id)->update_status('completed'); }" % json.dumps(order_ids).replace("[", "array(").replace("]", ")"))
    sent = [m for m in mails()[before:] if "anna@example.com" in str(m["to"])]
    assert len(sent) == 3
    assert [("Number candle" in m["message"]) for m in sent] == [True, True, False]
    assert "Ciparu svece" in sent[2]["message"]


def test_email_falls_back_when_language_pack_missing(ids, wpcli, option):
    """Important 2: enabled language without an installed pack → email in the site language, not broken."""
    option("mm_lang_enabled", ["en", "et"])
    s = shopper("/en/product/ciparu-svece-5/")
    add_item(s, ids["simple"])
    oid = checkout(s)
    wpcli("eval", f'$o = wc_get_order({oid}); $o->update_meta_data("_mm_lang", "et"); $o->save();')
    before = len(mails())
    wpcli("eval", f'wc_get_order({oid})->update_status("completed");')
    sent = [m for m in mails()[before:] if "anna@example.com" in str(m["to"])]
    assert sent and "pasūtījums" in sent[0]["subject"]


def test_untranslated_products_hidden_on_en_archive():
    """Important 3: English listings show only translated products; links to untranslated go to lv."""
    html = get("/en/product-category/ciparu-sveces/").text
    assert "Galda svece" not in html
    assert "/en/product/galda-svece/" not in html


def test_canonical_redirect_keeps_language():
    """Important 4: WordPress canonical redirects stay in the visitor's language."""
    r = get("/en/product/ciparu-svece-5", allow_redirects=False)
    assert r.status_code == 301
    assert r.headers["Location"] == SITE + "/en/product/ciparu-svece-5/"


def test_store_api_products_ignore_cookie():
    """Important 5a: cacheable Store API product data never depends on the visitor cookie."""
    r = requests.get(SITE + "/wp-json/wc/store/v1/products", cookies={"mm_lang": "en"}, timeout=60)
    names = [p["name"] for p in r.json()]
    assert "Ciparu svece “5”" in names and "Number candle “5”" not in names


def test_language_cookie_set_by_page_script():
    """Important 5b: pages do not send Set-Cookie (cache-friendly); an inline script sets mm_lang."""
    r = get("/en/product/ciparu-svece-5/")
    assert "mm_lang" not in r.headers.get("Set-Cookie", "")
    assert re.search(r'document\.cookie\s*=\s*["\']mm_lang=en', r.text)


def test_translated_content_rendered_once(wpcli, ids):
    """Important 6: translated content passes the_content only once."""
    html = get("/en/piegade/?mm_cc=1").text
    assert "MMFILTER1" in html and "MMFILTER2" not in html


def test_legacy_lv_redirect_only_when_public(option):
    """Important 7: hidden mode leaves /lv/ URLs alone (GPTranslate still owns them)."""
    option("mm_lang_public", False)
    r = get("/lv/product/ciparu-svece-5/", allow_redirects=False)
    assert r.status_code != 301


def test_json_ld_translated():
    """Important 8: Rank Math JSON-LD product description is translated."""
    html = get("/en/product/ciparu-svece-5/").text
    ld = re.search(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S)
    assert ld, "no JSON-LD"
    assert "Īss latviešu apraksts." not in ld.group(1)
    assert "Latviešu apraksts ciparu svecei." not in ld.group(1)
