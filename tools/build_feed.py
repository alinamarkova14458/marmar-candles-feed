#!/usr/bin/env python3
"""Build the Google Merchant Center product feed straight from WooCommerce.

Reads every product from the public WooCommerce Store API of marmarcandles.com
and writes an RSS 2.0 feed with the Google `g:` namespace.

Usage:
    python3 tools/build_feed.py [output_path]      # default: feed/google-lv.xml

Only the Python standard library is used, so it runs as-is in GitHub Actions.
"""
import html
import json
import re
import sys
import time
import urllib.request
from xml.sax.saxutils import escape

SITE = "https://marmarcandles.com"
LANG_PREFIX = "/lv"  # product pages answer on /lv/...; the bare URL 301-redirects there
API = SITE + "/wp-json/wc/store/v1/products"
BRAND = "MarMar Candles"
DEFAULT_OUT = "feed/google-lv.xml"

# Google product taxonomy IDs (https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt)
GPC_BIRTHDAY_CANDLES = "4730"  # Arts & Entertainment > Party & Celebration > Party Supplies > Birthday Candles
GPC_CANDLES = "588"            # Home & Garden > Decor > Home Fragrances > Candles
GPC_CANDLE_HOLDERS = "2784"    # Home & Garden > Decor > Home Fragrance Accessories > Candle Holders
GPC_CRAFT_MOLDS = "8007"       # Arts & Entertainment > Hobbies & Creative Arts > Arts & Crafts > Crafting Patterns & Molds > Craft Molds

# Categories that describe availability rather than what the product is.
NON_TYPE_CATEGORIES = {"pieejams uzreiz"}
# Categories excluded from the feed (services / courses are not physical products).
EXCLUDED_CATEGORIES = {"apmācības un meistarklases"}

EMOJI_RE = re.compile(
    "[\U0001F000-\U0001FAFF☀-➿⬀-⯿️‍]+"
)
TAG_RE = re.compile(r"<[^>]+>")
SPACE_RE = re.compile(r"\s+")


def clean_category(name: str) -> str:
    return SPACE_RE.sub(" ", EMOJI_RE.sub("", html.unescape(name))).strip()


def strip_html(text: str) -> str:
    text = re.sub(r"<\s*(br|/p|/li|/h\d)\s*/?>", " ", text or "", flags=re.I)
    text = TAG_RE.sub(" ", text)
    return SPACE_RE.sub(" ", html.unescape(text)).strip()


def fetch_all(per_page: int = 100) -> list:
    products, page = [], 1
    while True:
        url = f"{API}?per_page={per_page}&page={page}"
        req = urllib.request.Request(url, headers={"User-Agent": "MarMarFeedBuilder/1.0"})
        for attempt in range(4):
            try:
                with urllib.request.urlopen(req, timeout=60) as r:
                    batch = json.load(r)
                    total_pages = int(r.headers.get("X-WP-TotalPages", "1"))
                break
            except Exception:
                if attempt == 3:
                    raise
                time.sleep(2 ** (attempt + 1))
        products += batch
        if page >= total_pages or not batch:
            return products
        page += 1


def money(minor: str, unit: int) -> float:
    return int(minor) / (10 ** unit)


def landing_url(permalink: str) -> str:
    if permalink.startswith(SITE + "/") and not permalink.startswith(SITE + LANG_PREFIX + "/"):
        return SITE + LANG_PREFIX + permalink[len(SITE):]
    return permalink


def google_category(cats: list, name: str) -> str:
    low = " ".join(cats).lower() + " " + name.lower()
    if "veidne" in low:
        return GPC_CRAFT_MOLDS
    if "svečtur" in low or "svecturi" in low:
        return GPC_CANDLE_HOLDERS
    if any(w in low for w in ("dzimšanas", "cipar", "toper", "tortes sveces", "kūk", "tort")):
        return GPC_BIRTHDAY_CANDLES
    return GPC_CANDLES


def season_label(cats: list, name: str) -> str:
    low = " ".join(cats).lower() + " " + name.lower()
    if "ziemassvēt" in low or "jaungad" in low:
        return "ziemassvetki"
    if "kāz" in low:
        return "kazas"
    return "visu-gadu"


def to_item(p: dict):
    """Return (xml_string, None) for a feed item or (None, reason) when skipped."""
    if p.get("is_password_protected"):
        return None, "password protected"
    cats_raw = [clean_category(c["name"]) for c in p.get("categories", [])]
    if any(c.lower() in EXCLUDED_CATEGORIES for c in cats_raw):
        return None, "course / service"
    prices = p.get("prices") or {}
    unit = int(prices.get("currency_minor_unit", 2))
    rng = prices.get("price_range") or {}
    price_minor = rng.get("min_amount") or prices.get("price") or "0"
    regular_minor = prices.get("regular_price") or price_minor
    if not price_minor or int(price_minor) <= 0:
        return None, "no price"
    if not p.get("is_purchasable"):
        return None, "not purchasable"
    images = [i["src"] for i in p.get("images", []) if i.get("src")]
    if not images:
        return None, "no image"

    name = html.unescape(p["name"]).strip()
    type_cats = [c for c in cats_raw if c.lower() not in NON_TYPE_CATEGORIES] or ["Sveces"]
    description = strip_html(p.get("description")) or strip_html(p.get("short_description")) or name
    currency = prices.get("currency_code", "EUR")

    regular = money(regular_minor, unit)
    price = money(price_minor, unit)
    fields = [
        ("g:id", f"wc_{p['id']}"),
        ("g:title", name[:150]),
        ("g:description", description[:5000]),
        ("g:link", landing_url(p["permalink"])),
        ("g:image_link", images[0]),
    ]
    fields += [("g:additional_image_link", u) for u in images[1:11]]
    fields += [
        ("g:availability", "in_stock" if p.get("is_in_stock") else "out_of_stock"),
        ("g:price", f"{max(regular, price):.2f} {currency}"),
    ]
    if p.get("on_sale") and price < regular:
        fields.append(("g:sale_price", f"{price:.2f} {currency}"))
    fields += [
        ("g:condition", "new"),
        ("g:brand", BRAND),
        ("g:identifier_exists", "no"),
        ("g:google_product_category", google_category(type_cats, name)),
        ("g:product_type", " > ".join(["Sveces"] + type_cats[:1]) if type_cats[0] != "Sveces" else "Sveces"),
        ("g:custom_label_0", season_label(cats_raw, name)),
        ("g:custom_label_1", type_cats[0]),
    ]
    if p.get("sku"):
        fields.append(("g:mpn", p["sku"]))
    body = "\n".join(f"      <{k}>{escape(str(v))}</{k}>" for k, v in fields)
    return f"    <item>\n{body}\n    </item>", None


def build(out_path: str) -> None:
    products = fetch_all()
    items, skipped = [], {}
    for p in products:
        xml, reason = to_item(p)
        if xml:
            items.append(xml)
        else:
            skipped[reason] = skipped.get(reason, 0) + 1
    if len(items) < 50:
        # Guard against publishing a near-empty feed if the shop API misbehaves.
        raise SystemExit(f"Only {len(items)} items built from {len(products)} products; refusing to write feed.")

    feed = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">\n'
        "  <channel>\n"
        f"    <title>{escape(BRAND)}</title>\n"
        f"    <link>{SITE}{LANG_PREFIX}/</link>\n"
        "    <description>MarMar Candles produktu plūsma (no WooCommerce)</description>\n"
        + "\n".join(items)
        + "\n  </channel>\n</rss>\n"
    )
    import os

    os.makedirs(os.path.dirname(out_path) or ".", exist_ok=True)
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(feed)
    print(f"Products in shop: {len(products)}; items in feed: {len(items)}; skipped: {skipped}")
    print(f"Wrote {out_path}")


if __name__ == "__main__":
    build(sys.argv[1] if len(sys.argv) > 1 else DEFAULT_OUT)
