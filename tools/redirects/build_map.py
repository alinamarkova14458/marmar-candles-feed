"""Build a one-hop 301 redirect map for old (Mozello + deleted WP) URLs.

Input: redir/traced.json (GSC pages + current redirect chain), redir/targets.json (live WP/Woo URLs).
Output: redir/map.json with source path, destination, reason, confidence, traffic.
"""
import difflib, json, re, urllib.parse, collections

D = "https://marmarcandles.com/lv/"
pages = json.load(open("redir/traced.json"))
T = json.load(open("redir/targets.json"))

def lv(link):
    p = urllib.parse.urlparse(link).path
    if not p.startswith("/lv/"):
        p = "/lv" + p
    return "https://marmarcandles.com" + p

CAT = {c["slug"]: lv(c["link"]) for c in T["cats"]}
COURSE = [v for k, v in CAT.items() if "apmacibas" in k][0]
PAGE = {p["slug"]: lv(p["link"]) for p in T["pages"]}
POST = {p["slug"]: lv(p["link"]) for p in T["posts"]}
PROD = {p["slug"]: lv(p["link"]) for p in T["products"]}
PROD_CATS = {p["slug"]: p["cats"] for p in T["products"]}
HOME, SHOP, BLOG = D, PAGE["shop"], PAGE["blogs"]
WEDDING = PAGE["kazu-sveces"]

# Old category segment (Mozello LV + EN) -> live category. Checked against the live category list.
CATMAP = [
    (r"svecu-veidnes|candle-molds|veidnes|mold", CAT["svecu-veidnes"]),
    (r"materiali|candle-making", CAT["materiali-svecu-izgatavosanai"]),
    (r"ziemassvetk|christmas|xmas", CAT["ziemassvetku-sveces"]),
    (r"kazu|kāzu|wedding", CAT["kazu-sveces"]),
    (r"ciparu|number", CAT["ciparu-sveces"]),
    (r"toperi|topper", CAT["kuku-toperi"]),
    (r"apmacib|meistarklas|course|workshop|workbook", COURSE),
    (r"pieejam|ready-to-ship", CAT["sveces-pieejamas-uzreiz"]),
    (r"galda", CAT["galda-sveces"]),
    (r"dizaina|stavsveces", CAT["dizaina-sveces"]),
    (r"davan|gift", CAT["davanas"]),
    (r"dzimsanas-dienas", CAT["dzimsanas-dienas-sveces"]),
    (r"svecites-tortem|tortes-sveces|birthday|cake-candles|kuku|kūku", CAT["tortes-sveces"]),
    (r"container|interjer|home|decor|bath|aroma|sojas|palo", CAT["interjera-sveces"]),
]

STATIC = {  # old Mozello pages without a slug match
    "": HOME, "home-2": HOME, "home": HOME, "lt": HOME, "kalkulators": HOME,
    "shop": SHOP, "veikals": SHOP, "blog": BLOG, "blog-2": BLOG,
    "kontakti": PAGE["kontakti"], "contact-us-1": PAGE["kontakti"],
    "piegade": PAGE["piegade"], "delivery": PAGE["piegade"],
    "faq": PAGE["biezak-uzdotie-jautajumi"], "buj": PAGE["biezak-uzdotie-jautajumi"],
    "privacy-policy": PAGE["privatuma-politika"], "privatuma-politika": PAGE["privatuma-politika"],
    "regulations": PAGE["noteikumi"], "noteikumi": PAGE["noteikumi"],
    "course": COURSE, "free-workshop": COURSE, "free-workshop-signup": COURSE, "workbook": COURSE,
    "cooperation": PAGE["sadarbibas-partneriem"], "sadarbibas-partneriem": PAGE["sadarbibas-partneriem"],
    "korporativie-kazu-sveces": PAGE["sadarbibas-partneriem"],
    "ai-reference": PAGE["ai-atsauces-lapa-lielajiem-valodas-modeliem-llm"],
    "kazu-sveces-viesiem": WEDDING, "kazu-sveces": WEDDING,
    "dabigas-sveces": CAT["interjera-sveces"], "sveces": CAT["tortes-sveces"],
}

# English Mozello blog posts -> closest live Latvian content (topic match, checked by hand).
EN_BLOG = [
    (r"mold", CAT["svecu-veidnes"]),
    (r"bento", CAT["svecu-veidnes"]),
    (r"topper", POST.get("kuku-topperi-dzimsanas-dienas-kukai", BLOG)),
    (r"number", POST.get("ciparu-sveces-dzimsanas-dienai", BLOG)),
    (r"birthday|cake|candle", POST.get("ka-izveleties-sveces-kukai", BLOG)),
]

def by_cat(text):
    for rx, dest in CATMAP:
        if re.search(rx, text):
            return dest
    return None

import unicodedata
COLORS = ["sarkan","zal","zil","roz","balt","meln","zelt","sudrab","dzelten","violet","oranz","pelek","bez","fuksij","kobalt","purpur","pastel","neitral","zals","bronz","varav"]
def slugify(t):
    t = unicodedata.normalize("NFKD", re.sub(r"&#\d+;|&[a-z]+;", " ", t)).encode("ascii","ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", t).strip("-")
PROD_KEYS = {}
for p in T["products"]:
    PROD_KEYS[p["slug"]] = lv(p["link"])
    PROD_KEYS[slugify(p["name"])] = lv(p["link"])
DF = collections.Counter(t for k in PROD_KEYS for t in set(k.split("-")))
STOP = {"un","ar","no","kukam","kuku","kukas","tortem","tortei","sveces","svece","svecites","svecite","dzimsanas","dienas","komplekts","gab","bisu","vaska","dabiga","sojas","forma","formas","toperis"}
def rare_ok(slug, cand):
    ct = set(cand.split("-"))
    rare = [t for t in set(slug.split("-")) if len(t) >= 4 and t not in STOP and not t.isdigit() and 0 < DF[t] <= 6]
    missing_catalog = [t for t in set(slug.split("-")) if len(t) >= 4 and t not in STOP and not re.search(r"\d", t) and DF[t] == 0]
    if any(t not in ct for t in rare):
        return False
    return len(missing_catalog) <= 1  # source names a feature no product has -> likely discontinued
def nums(slug, strict):
    core = slug if strict else re.sub(r"-\d$", "", slug)
    return set(re.findall(r"\d+", core))
def colors(slug):
    return {c for c in COLORS if re.search(r"(^|-)" + c, slug)}
def match_product(slug):
    slug = slugify(slug)
    strict = "cipar" in slug
    cands = difflib.get_close_matches(slug, list(PROD_KEYS), n=8, cutoff=0.6)
    want_n, want_c = nums(slug, strict), colors(slug)
    st = set(slug.split("-"))
    best = None
    for c in cands:
        if want_n and nums(c, True) != want_n and not want_n <= nums(c, True):
            continue
        if want_n and strict and nums(c, True) != want_n:
            continue
        if want_c and not want_c <= colors(c):
            continue
        if not rare_ok(slug, c):
            continue
        ct = set(c.split("-"))
        score = 0.5 * difflib.SequenceMatcher(None, slug, c).ratio() + 0.5 * len(st & ct) / len(st | ct)
        if best is None or score > best[0]:
            best = (score, c)
    if best and best[0] >= 0.62:
        return best[1], PROD_KEYS[best[1]], best[0]
    return None, None, 0

def fuzzy(slug, pool, cutoff):
    m = difflib.get_close_matches(slug, list(pool), n=1, cutoff=cutoff)
    return (m[0], pool[m[0]]) if m else (None, None)

def is_live_final(hops):
    last = hops[-1]
    return last["status"] == 200 and "area.lv" not in last["url"]

def normalize_dest(url):
    p = urllib.parse.urlparse(url)
    path = p.path if p.path.endswith("/") else p.path + "/"
    return "https://marmarcandles.com" + path

# Numeric Mozello category ids seen next to a category name: /shop/category/425949/wedding-candles/
ID2CAT = {}
for p in pages:
    m = re.search(r"/category/(\d{5,})/([a-z0-9-]+)", p["url"])
    if m:
        ID2CAT[m.group(1)] = m.group(2)
# item id -> category text from /shop/item/<cat...>/<id>/
for p in pages:
    m = re.search(r"/shop/item/(.+?)/(\d{6,})/?$", p["url"])
    if m:
        ID2CAT.setdefault("item" + m.group(2), m.group(1))

for p in pages:
    m = re.search(r"/shop/params/category/(\d+)/item/(\d+)", p["url"])
    if m and ("item" + m.group(2)) in ID2CAT:
        ID2CAT.setdefault(m.group(1), ID2CAT["item" + m.group(2)])
ID2CAT.setdefault("426117", "candle-molds")

def decide(p):
    url = p["url"]
    u = urllib.parse.urlparse(url)
    path = urllib.parse.unquote(u.path)
    segs = [s for s in path.split("/") if s]
    hops = p["hops"]
    final = hops[-1]
    n_redirects = len(hops) - 1
    via_area = any("area.lv" in h["url"] for h in hops)
    # Only fix what is broken: final 404/410/5xx, or >1 redirect, or via area.lv.
    # Single-hop www->non-www or GPTranslate /lv/ hops of live WP pages are out of scope (canonical task).
    if final["status"] == 200 and n_redirects <= 1 and not via_area:
        return None
    if segs and segs[0] in ("lv", "en", "ru"):
        lang, segs = segs[0], segs[1:]
    if segs and segs[0] == "files":
        return None  # Mozello image files: let them 404, no page equivalent
    if any(s in ("cart", "checkout", "account-details", "account-reviews", "password-forgot", "go") for s in segs):
        return None  # account/cart utility URLs: not worth redirecting
    src = u.path if u.path else "/"

    if len(segs) == 1 and segs[0] in STATIC and segs[0] not in ("shop", "veikals", "blog", "blog-2"):
        return u.path, STATIC[segs[0]], "страница", "высокая"
    # 1) Chain that already ends on a live page: collapse to one hop.
    if final["status"] == 200 and not via_area and not final["url"].rstrip("/").endswith("/lv/home"):
        return src, normalize_dest(final["url"]), "цепочка → один редирект на ту же страницу", "высокая"
    if final["status"] == 200 and via_area:
        return src, normalize_dest(final["url"].replace("marmarcandles.area.lv", "marmarcandles.com")), "цепочка через area.lv → один редирект", "высокая"

    first = segs[0] if segs else ""
    last = segs[-1] if segs else ""
    # 2) Product URLs: try exact/fuzzy product slug match.
    if first in ("veikals", "shop", "product") and ("item" in segs or first == "product"):
        if not last.isdigit():
            if last in PROD:
                return src, PROD[last], "товар найден по адресу", "высокая"
            s, dest, sc = match_product(last)
            if dest:
                return src, dest, f"товар похож ({sc:.2f}): {s}", "высокая" if sc >= 0.8 else "средняя"
        cat_text = " ".join(segs[1:-1]) + " " + ID2CAT.get("item" + last, "")
        for k, v in ID2CAT.items():
            if f"/{k}/" in path:
                cat_text += " " + v
        dest = by_cat(cat_text + " " + last)
        if dest:
            return src, dest, "товар не найден → его категория", "средняя"
        return src, SHOP, "товар не найден → магазин", "низкая"
    # 3) Category URLs.
    if first in ("veikals", "shop", "product-category", "sveces", "category"):
        text = " ".join(segs[1:])
        for k, v in ID2CAT.items():
            if k in segs:
                text += " " + v
        if any(s in ("all", "featured") for s in segs) or len(segs) == 1:
            return src, SHOP if first != "sveces" else CAT["tortes-sveces"], "весь магазин", "высокая"
        dest = by_cat(text)
        if dest:
            return src, dest, "категория", "высокая"
        return src, SHOP, "категория не найдена → магазин", "низкая"
    # 4) Blog posts.
    if first in ("blog", "blog-2"):
        if len(segs) == 1:
            return src, BLOG, "блог", "высокая"
        if first == "blog-2":
            for rx, dest in EN_BLOG:
                if re.search(rx, last):
                    return src, dest, "англ. статья → ближайшая латышская тема", "средняя"
            return src, BLOG, "англ. статья → блог", "низкая"
        slug = re.sub(r"-20\d\d-gada", "", last)
        pool = {re.sub(r"-20\d\d-gada", "", k): v for k, v in POST.items()}
        if slug in pool:
            return src, pool[slug], "статья найдена", "высокая"
        s, dest = fuzzy(slug, pool, 0.6)
        if dest:
            return src, dest, f"статья похожа: {s}", "средняя"
        dest = by_cat(last)
        if dest:
            return src, dest, "статьи нет → категория по теме", "низкая"
        return src, BLOG, "статьи нет → блог", "низкая"
    # 5) Deleted WP posts/pages at root.
    if first in POST:
        return src, POST[first], "статья", "высокая"
    if first in STATIC:
        return src, STATIC[first], "страница", "высокая"
    if first in PAGE:
        return src, PAGE[first], "страница", "высокая"
    dest = by_cat(path)
    if dest:
        return src, dest, "по теме адреса", "низкая"
    return src, HOME, "не найдено → главная", "низкая"

LIVE = set()
for k in ("products", "cats", "pages", "posts"):
    for x in T[k]:
        pth = urllib.parse.urlparse(x["link"]).path.rstrip("/")
        LIVE.add(pth); LIVE.add("/lv" + pth); LIVE.add("/en" + pth)
LIVE.add("/lv")

out = {}
skipped = collections.Counter()
for p in pages:
    r = decide(p)
    if not r:
        skipped["ok_or_ignored"] += 1
        continue
    src, dest, reason, conf = r
    src = src.rstrip("/") or "/"
    if src in LIVE or src == "/":
        skipped["live_page_protected"] += 1
        continue
    e = out.setdefault(src, {"source": src, "destination": dest, "reason": reason, "confidence": conf,
                             "clicks": 0, "impressions": 0, "current": "", "hosts": set()})
    e["clicks"] += p["clicks"]; e["impressions"] += p["impr"]
    e["hosts"].add(urllib.parse.urlparse(p["url"]).netloc)
    chain = " → ".join(str(h["status"]) for h in p["hops"])
    if len(chain) > len(e["current"]):
        e["current"] = chain + (" (через area.lv)" if any("area.lv" in h["url"] for h in p["hops"]) else "")
# Manual overrides after human review: source path -> (destination, reason)
OVERRIDES = {
    "/blog/params/post/4414482/palo-santo-kocini-cels-uz-harmoniju-un-attirisanos":
        (PROD["palo-santo-kocini-50g"], "ручная проверка: статья → товар palo santo"),
}
for srcp, (dest, why) in OVERRIDES.items():
    if srcp in out:
        out[srcp].update(destination=dest, reason=why, confidence="высокая")
rows = sorted(out.values(), key=lambda e: (-e["clicks"], -e["impressions"]))
for e in rows:
    e["hosts"] = sorted(e["hosts"])
json.dump(rows, open("redir/map.json", "w"), ensure_ascii=False, indent=1)
print(len(rows), "redirects;", dict(skipped))
print(collections.Counter(e["confidence"] for e in rows))
print(collections.Counter(e["reason"].split(":")[0] for e in rows))
