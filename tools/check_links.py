#!/usr/bin/env python3
"""Find broken internal links and images on marmarcandles.com.

Reads every page from the sitemap, collects links and images that point to the
site itself, plus every link/image in feed/google-lv.xml, and checks them.
Writes a Markdown report (default: link-report.md) and exits with code 1 when
broken links were found. Standard library only.

Usage:
    python3 tools/check_links.py [report_path]
"""
import concurrent.futures as cf
import html
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict

SITE = "https://marmarcandles.com"
HOSTS = {"marmarcandles.com", "www.marmarcandles.com"}
SITEMAP = SITE + "/sitemap_index.xml"
FEED = "feed/google-lv.xml"
UA = "Mozilla/5.0 (compatible; MarMarLinkChecker/1.0)"
WORKERS = 6
# Pages that are fine to skip: cart, account, technical endpoints, theme demo pages.
SKIP = re.compile(
    r"add-to-cart=|/cart|/checkout|/my-account|/wp-json|/wp-admin|/feed/?$|/xmlrpc|"
    r"\?replytocom=|/wp-login|#|/comments/feed|/left-sidebar|/right-sidebar|/shop-2|/cart-2|/checkout-2|/my-account-2"
)
LINK_RE = re.compile(r'(?:href|src)\s*=\s*["\']([^"\'#]+)["\']', re.I)


def ascii_url(url):
    """Percent-encode non-ASCII characters (e.g. Cyrillic image names) so urllib can request them."""
    return urllib.parse.quote(url, safe=":/?&=%#+,;@!$'()*~[]")


def get(url, method="GET"):
    req = urllib.request.Request(ascii_url(url), method=method, headers={"User-Agent": UA})
    for attempt in range(3):
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                body = r.read() if method == "GET" else b""
                return r.status, body
        except urllib.error.HTTPError as e:
            if e.code in (429, 500, 502, 503, 504) and attempt < 2:
                time.sleep(3 * (attempt + 1))
                continue
            return e.code, b""
        except Exception as e:  # timeouts, DNS, TLS
            if attempt < 2:
                time.sleep(3 * (attempt + 1))
                continue
            return f"{type(e).__name__}", b""


def sitemap_pages():
    status, body = get(SITEMAP)
    if status != 200:
        sys.exit(f"Sitemap not reachable: {status}")
    maps = re.findall(r"<loc>([^<]+)</loc>", body.decode("utf-8", "replace"))
    pages = []
    for m in maps:
        st, b = get(html.unescape(m))
        if st == 200:
            pages += [html.unescape(u) for u in re.findall(r"<loc>([^<]+)</loc>", b.decode("utf-8", "replace"))]
    return sorted({p for p in pages if not SKIP.search(p)})


def internal(url, base):
    u = urllib.parse.urljoin(base, html.unescape(url.strip()))
    p = urllib.parse.urlparse(u)
    if p.scheme not in ("http", "https") or p.hostname not in HOSTS:
        return None
    if SKIP.search(u):
        return None
    return urllib.parse.urlunparse(p._replace(fragment=""))


def main(report_path="link-report.md"):
    pages = sitemap_pages()
    print(f"Pages in sitemap: {len(pages)}")
    found_on = defaultdict(set)   # link -> pages that contain it
    status = {}

    def crawl(page):
        st, body = get(page)
        links = set()
        if st == 200:
            for raw in LINK_RE.findall(body.decode("utf-8", "replace")):
                u = internal(raw, page)
                if u:
                    links.add(u)
        return page, st, links

    with cf.ThreadPoolExecutor(WORKERS) as ex:
        for page, st, links in ex.map(crawl, pages):
            status[page] = st
            for u in links:
                found_on[u].add(page)
    for p in pages:
        found_on[p].add("(sitemap)")

    try:
        feed = open(FEED, encoding="utf-8").read()
        for u in re.findall(r"<g:(?:link|image_link|additional_image_link)>([^<]+)<", feed):
            found_on[html.unescape(u)].add("(фид Google)")
    except FileNotFoundError:
        pass

    todo = [u for u in found_on if u not in status]
    print(f"Unique links to check: {len(todo)}")

    def check(u):
        st, _ = get(u, "HEAD")
        if st in (403, 405) or not isinstance(st, int):
            st, _ = get(u)
        return u, st

    with cf.ThreadPoolExecutor(WORKERS) as ex:
        for u, st in ex.map(check, todo):
            status[u] = st

    # WordPress shortlinks (/?p=123) live only in <link rel="shortlink"> and all fail the same way:
    # report them as one line instead of hundreds of rows.
    shortlinks = {u for u, st in status.items() if st != 200 and re.search(r"/\?p=\d+$", u)}
    broken = {u: st for u, st in status.items() if st != 200 and u not in shortlinks}
    lines = [
        "# Проверка ссылок marmarcandles.com",
        f"Страниц в карте сайта: {len(pages)}. Проверено ссылок и картинок: {len(status)}. "
        f"**Битых: {len(broken)}.**\n",
    ]
    if shortlinks:
        lines.append(
            f"> Отдельно: {len(shortlinks)} коротких ссылок WordPress вида `/?p=123` отвечают 404. "
            "Посетители их не видят (они только в коде страницы), но Google может на них натыкаться.\n"
        )
    if broken:
        lines.append("| Ссылка | Ответ | Где встречается |\n|---|---|---|")
        for u, st in sorted(broken.items(), key=lambda kv: (str(kv[1]), kv[0])):
            where = sorted(found_on.get(u, []))
            shown = ", ".join(w.replace(SITE, "") or "/" for w in where[:3])
            if len(where) > 3:
                shown += f" и ещё {len(where) - 3}"
            lines.append(f"| {u.replace(SITE, '')} | {st} | {shown} |")
    with open(report_path, "w", encoding="utf-8") as f:
        f.write("\n".join(lines) + "\n")
    print(f"Broken: {len(broken)}. Report: {report_path}")
    return 1 if broken else 0


if __name__ == "__main__":
    sys.exit(main(*sys.argv[1:2]))
