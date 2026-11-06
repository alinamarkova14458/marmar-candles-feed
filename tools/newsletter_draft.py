#!/usr/bin/env python3
"""Создать (или обновить) черновик рассылки на сайте из newsletters/<name>.json.

Нужны WP_USER и WP_APP_PASSWORD и установленный плагин marmar-newsletter.
Скрипт только создаёт черновик и по флагу --test шлёт тест на адреса из настроек
плагина. Отправить всем можно только кнопкой в админке.

    python3 tools/newsletter_draft.py newsletters/2026-11-ziemassvetki.json [--test]
"""
import json
import os
import sys
from pathlib import Path

import requests

SITE = os.environ.get("WP_SITE", "https://www.marmarcandles.com")


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    spec_path = Path(sys.argv[1])
    spec = json.loads(spec_path.read_text())
    content = (spec_path.parent / spec["content_file"]).read_text()
    auth = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])
    api = f"{SITE}/wp-json/wp/v2/marmar-campaigns"

    existing = requests.get(api, params={"slug": spec["slug"], "status": "any", "context": "edit"}, auth=auth, timeout=30)
    existing.raise_for_status()
    body = {
        "title": spec["title"],
        "slug": spec["slug"],
        "content": content,
        "status": "draft",
        "meta": {
            "_mm_subject": spec["subject"],
            "_mm_preheader": spec["preheader"],
            "_mm_audience": spec.get("audience", "consent"),
        },
    }
    if existing.json():
        post_id = existing.json()[0]["id"]
        body.pop("status")  # не трогаем статус уже существующей рассылки
        r = requests.post(f"{api}/{post_id}", json=body, auth=auth, timeout=30)
    else:
        r = requests.post(api, json=body, auth=auth, timeout=30)
    r.raise_for_status()
    post_id = r.json()["id"]
    print(f"Черновик: {SITE}/wp-admin/post.php?post={post_id}&action=edit")

    if "--test" in sys.argv:
        t = requests.post(f"{SITE}/wp-json/marmar-newsletter/v1/campaigns/{post_id}/test", auth=auth, timeout=60)
        print("Тест:", t.status_code, t.text)


if __name__ == "__main__":
    main()
