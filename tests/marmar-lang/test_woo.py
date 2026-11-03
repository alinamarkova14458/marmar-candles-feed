import json
import os

import requests

from conftest import DIR, SITE

ADDR = {"first_name": "Anna", "last_name": "Test", "address_1": "Brīvības iela 1", "city": "Rīga",
        "postcode": "LV-1010", "country": "LV", "email": "anna@example.com", "phone": "+37120000000"}


def shopper(lang_path):
    s = requests.Session()
    s.get(SITE + lang_path, timeout=60)  # sets mm_lang cookie
    r = s.get(SITE + "/wp-json/wc/store/v1/cart", timeout=60)
    s.headers["Nonce"] = r.headers.get("Nonce", "")
    return s


def add_item(s, pid):
    r = s.post(SITE + "/wp-json/wc/store/v1/cart/add-item", json={"id": pid, "quantity": 1}, timeout=60)
    assert r.status_code == 201, r.text
    return r.json()


def checkout(s):
    r = s.post(SITE + "/wp-json/wc/store/v1/checkout", timeout=60, json={
        "billing_address": ADDR, "shipping_address": {k: v for k, v in ADDR.items() if k != "email"},
        "payment_method": "cod"})
    assert r.status_code == 200, r.text
    return r.json()["order_id"]


def mails():
    path = os.path.join(DIR, "mail.log")
    if not os.path.exists(path):
        return []
    with open(path) as f:
        return [json.loads(line) for line in f if line.strip()]


def test_store_api_cart_language(ids):
    s = shopper("/en/product/ciparu-svece-5/")
    cart = add_item(s, ids["simple"])
    assert cart["items"][0]["name"] == "Number candle “5”"
    s.get(SITE + "/product/ciparu-svece-5/", timeout=60)  # back to Latvian
    cart = s.get(SITE + "/wp-json/wc/store/v1/cart", timeout=60).json()
    assert cart["items"][0]["name"] == "Ciparu svece “5”"


def test_cart_checkout_pages(ids):
    s = shopper("/en/product/ciparu-svece-5/")
    add_item(s, ids["simple"])  # empty checkout redirects to cart by WooCommerce design
    for path in ("/en/cart/", "/en/checkout/"):
        r = s.get(SITE + path, timeout=60, allow_redirects=False)
        assert r.status_code == 200, path
        assert '<html lang="en-GB"' in r.text, path


def test_order_language_saved(ids, wpcli):
    s = shopper("/en/product/ciparu-svece-5/")
    add_item(s, ids["simple"])
    oid = checkout(s)
    assert wpcli("eval", f'echo wc_get_order({oid})->get_meta("_mm_lang");') == "en"


def test_order_email_language(ids):
    before = len(mails())
    s = shopper("/en/product/ciparu-svece-5/")
    add_item(s, ids["simple"])
    checkout(s)
    new = [m for m in mails()[before:] if "anna@example.com" in str(m["to"])]
    assert new, "no customer email"
    assert "order has been received" in new[0]["subject"]
    assert "Number candle" in new[0]["message"]


def test_lv_order_email_latvian(ids):
    before = len(mails())
    s = shopper("/product/ciparu-svece-5/")
    add_item(s, ids["simple"])
    checkout(s)
    new = [m for m in mails()[before:] if "anna@example.com" in str(m["to"])]
    assert new, "no customer email"
    assert "order has been received" not in new[0]["subject"]
    assert "Ciparu svece" in new[0]["message"]


def test_admin_status_change_email_keeps_order_language(ids, wpcli):
    """Order placed in English, completed later by the admin (Latvian request) → English email."""
    s = shopper("/en/product/ciparu-svece-5/")
    add_item(s, ids["simple"])
    oid = checkout(s)
    before = len(mails())
    wpcli("eval", f'wc_get_order({oid})->update_status("completed");')
    new = [m for m in mails()[before:] if "anna@example.com" in str(m["to"])]
    assert new, "no completed email"
    assert new[0]["subject"].startswith("Your ")  # English template (exact wording varies by WooCommerce version)
    assert "pasūtījums" not in new[0]["subject"]
    assert "Number candle" in new[0]["message"]
