#!/usr/bin/env python3
"""Create or update a scheduled blog post on marmarcandles.com.

Usage: python3 tools/blog_publish.py blog/posts/<post>.py [--draft]

The post module must define POST = {title, slug, excerpt, categories, featured_media,
date_gmt ("YYYY-MM-DDTHH:MM:SS"), seo_title, seo_description, focus_keyword} and CONTENT (block markup).
Checks every link/image (must answer 200), creates the post (status "future" when date_gmt is in the
future, "publish" otherwise; "draft" with --draft), or updates the existing post with the same slug,
then writes Rank Math SEO fields. Credentials: WP_USER + WP_APP_PASSWORD (never printed).
"""
import concurrent.futures as cf
import datetime as dt
import importlib.util
import os
import re
import sys

import requests

API = "https://marmarcandles.com/wp-json"
AUTHOR_ID = 1  # "MarMar CANDLES"
H = {"User-Agent": "Mozilla/5.0 (compatible; MarMarBlog/1.0)"}


def load(path):
    sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(path))))
    spec = importlib.util.spec_from_file_location("post", path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod.POST, mod.CONTENT


def check_links(content, skip=()):
    # skip: links to our own posts that are scheduled to go live before this one
    urls = sorted(set(re.findall(r'(?:href|src)="([^"]+)"', content)) - set(skip))

    def chk(u):
        try:
            return u, requests.head(u, headers=H, allow_redirects=False, timeout=90).status_code
        except requests.RequestException as e:
            return u, type(e).__name__
    with cf.ThreadPoolExecutor(4) as ex:
        res = list(ex.map(chk, urls))
    return len(urls), [r for r in res if r[1] != 200]


def main():
    path = sys.argv[1]
    draft = "--draft" in sys.argv
    post, content = load(path)
    n, bad = check_links(content, post.get("scheduled_links", []))
    print(f"links/images: {n}, broken: {bad}")
    if bad:
        sys.exit("Fix broken links first.")
    auth = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])
    when = dt.datetime.fromisoformat(post["date_gmt"]).replace(tzinfo=dt.timezone.utc)
    status = "draft" if draft else ("future" if when > dt.datetime.now(dt.timezone.utc) else "publish")
    body = {"title": post["title"], "slug": post["slug"], "content": content, "excerpt": post["excerpt"],
            "author": AUTHOR_ID, "categories": post["categories"], "featured_media": post["featured_media"],
            "comment_status": "open", "status": status, "date_gmt": post["date_gmt"]}
    existing = requests.get(f"{API}/wp/v2/posts", params={"slug": post["slug"], "status": "publish,future,draft",
                            "_fields": "id"}, auth=auth, timeout=60).json()
    url = f"{API}/wp/v2/posts/{existing[0]['id']}" if existing else f"{API}/wp/v2/posts"
    r = requests.post(url, json=body, auth=auth, timeout=120)
    r.raise_for_status()
    j = r.json()
    meta = {"rank_math_title": post["seo_title"], "rank_math_description": post["seo_description"],
            "rank_math_focus_keyword": post["focus_keyword"]}
    m = requests.post(f"{API}/rankmath/v1/updateMeta", json={"objectID": j["id"], "objectType": "post", "meta": meta},
                      auth=auth, timeout=60)
    print(f"{'updated' if existing else 'created'} id={j['id']} status={j['status']} date_gmt={j['date_gmt']} "
          f"link={j['link']} rankmath={m.status_code}")


if __name__ == "__main__":
    main()
