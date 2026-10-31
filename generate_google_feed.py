"""Builds a Google Merchant Center feed from the live WooCommerce store.

Data source: the public WooCommerce Store API of marmarcandles.com (no keys needed).
Output: google_feed.xml (RSS 2.0, g: namespace), fetched daily by Merchant Center.
"""
import html
import re
import sys
import time
import xml.etree.ElementTree as ET

import requests

SITE = "https://marmarcandles.com"
API = f"{SITE}/wp-json/wc/store/v1/products"
OUT = "google_feed.xml"
G = "http://base.google.com/ns/1.0"
BRAND = "MarMar Candles"
COUNTRY = "LV"
GOOGLE_CATEGORY = "Home & Garden > Decor > Candles"
HEADERS = {"User-Agent": "MarMarCandles-FeedBuilder/1.0"}

ET.register_namespace("g", G)
session = requests.Session()
session.headers.update(HEADERS)


def get(url, params=None):
    for attempt in range(4):
        try:
            r = session.get(url, params=params, timeout=60)
            r.raise_for_status()
            return r
        except requests.RequestException:
            if attempt == 3:
                raise
            time.sleep(2 ** (attempt + 1))


def fetch_products():
    products, page = [], 1
    while True:
        r = get(API, {"per_page": 100, "page": page})
        products.extend(r.json())
        if page >= int(r.headers.get("X-WP-TotalPages", 1)):
            return products
        page += 1


def clean_text(raw, limit=5000):
    text = re.sub(r"<(br|/p|/li|/h\d)[^>]*>", "\n", raw or "", flags=re.I)
    text = re.sub(r"<[^>]+>", "", text)
    text = html.unescape(text).replace("\xa0", " ")
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n\s*\n+", "\n", text).strip()
    return text[:limit]


def lv_link(url):
    """Product pages live under /lv/; the API returns links without the prefix."""
    return re.sub(r"^(https?://[^/]+)/(?!lv/)", r"\1/lv/", url)


def price_str(prices, key="price"):
    minor = int(prices.get("currency_minor_unit", 2))
    value = int(prices[key]) / (10 ** minor)
    return f"{value:.2f} {prices.get('currency_code', 'EUR')}"


def add(item, tag, value):
    if value not in (None, ""):
        ET.SubElement(item, f"{{{G}}}{tag}").text = str(value)


def build_item(channel, p, *, item_id, title, prices, in_stock, link):
    item = ET.SubElement(channel, "item")
    add(item, "id", item_id)
    add(item, "title", clean_text(title, 150))
    add(item, "description", clean_text(p.get("description") or p.get("short_description") or p["name"]))
    add(item, "link", link)
    images = [i["src"] for i in p.get("images", []) if i.get("src")]
    if images:
        add(item, "image_link", images[0])
        for extra in images[1:11]:
            add(item, "additional_image_link", extra)
    add(item, "availability", "in_stock" if in_stock else "out_of_stock")
    add(item, "condition", "new")
    regular, current = int(prices["regular_price"]), int(prices["price"])
    if regular and current < regular:
        add(item, "price", price_str(prices, "regular_price"))
        add(item, "sale_price", price_str(prices, "price"))
    else:
        add(item, "price", price_str(prices, "price"))
    add(item, "brand", BRAND)
    add(item, "identifier_exists", "no")
    add(item, "google_product_category", GOOGLE_CATEGORY)
    cats = [clean_text(c["name"]).lstrip("🎂🔢🎄🎁💍✨🕯️ ").strip() for c in p.get("categories", [])]
    if cats:
        add(item, "product_type", " > ".join(cats))
    ship = ET.SubElement(item, f"{{{G}}}shipping")
    add(ship, "country", COUNTRY)


def main():
    products = fetch_products()
    rss = ET.Element("rss", {"version": "2.0"})
    channel = ET.SubElement(rss, "channel")
    ET.SubElement(channel, "title").text = "MarMar Candles"
    ET.SubElement(channel, "link").text = SITE
    ET.SubElement(channel, "description").text = "MarMar Candles — Google Merchant Center feed"

    count = skipped = 0
    for p in products:
        if not p.get("is_purchasable") or not p.get("prices", {}).get("price"):
            skipped += 1
            continue
        # One item per product. Variable products (the store has ~4 350 variation
        # combinations) are listed once with their lowest price — the "from" price
        # shown on the product page.
        build_item(channel, p, item_id=p["id"], title=p["name"], prices=p["prices"],
                   in_stock=p.get("is_in_stock", True), link=lv_link(p["permalink"]))
        count += 1

    ET.indent(rss)
    ET.ElementTree(rss).write(OUT, encoding="utf-8", xml_declaration=True)
    print(f"products: {len(products)}, feed items: {count}, skipped (not purchasable/no price): {skipped}")
    if count == 0:
        sys.exit("Feed is empty — refusing to publish.")


if __name__ == "__main__":
    main()
