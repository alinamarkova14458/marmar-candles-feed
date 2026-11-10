#!/usr/bin/env python3
"""Writes social/instagram/PLAN.md: Alina's view of scheduled and published Instagram posts.

Reads the queue and published.json; optional PRS_JSON = path to `gh pr list --json
number,title,url,files` output, to list posts waiting for her approval (open PRs).
"""
from __future__ import annotations

import json
import os
from datetime import datetime, timezone

from instagram_scheduler import QUEUE_GLOB, STATE_PATH, RIGA, load_queue, load_state, media, post_id

PLAN_PATH = os.path.join(os.path.dirname(STATE_PATH), "PLAN.md")
DAYS = ["пн", "вт", "ср", "чт", "пт", "сб", "вс"]
KIND = {"image": "фото", "carousel": "карусель", "reel": "рилс"}


def when(d: datetime) -> str:
    d = d.astimezone(RIGA)
    return f"{DAYS[d.weekday()]} {d:%d.%m %H:%M}"


def short(text: str, n: int = 110) -> str:
    t = " ".join(text.split()).replace("|", "/")
    return t if len(t) <= n else t[:n - 1] + "…"


def status(rec: dict | None) -> str:
    if not rec:
        return "⏳ запланирован"
    if rec.get("media_id"):
        return f"✅ [опубликован]({rec['permalink']})" if rec.get("permalink") else "✅ опубликован"
    if rec.get("skipped"):
        return f"⏭ пропущен: {rec['skipped']}"
    return f"⚠️ ошибка: {short(rec.get('error', ''), 80)}"


def row(it: dict, rec: dict | None) -> str:
    urls = media(it)
    thumbs = " ".join(f'<img src="{u}" width="90">' for u in urls[:3]) + (f" +{len(urls) - 3}" if len(urls) > 3 else "")
    kind = KIND.get(it.get("type", "image"), it.get("type", ""))
    if it.get("type") == "carousel":
        kind += f" ({len(urls)})"
    return f"| {when(it['_when'])} | {thumbs} | {kind} | {short(it.get('caption', ''))} | {status(rec)} |"


def render(items: list[dict], state: dict, prs: list[dict], now: datetime) -> str:
    head = "| Когда (Рига) | Фото | Тип | Подпись | Статус |\n|---|---|---|---|---|"
    for it in items:
        it["_id"] = post_id(it)
    upcoming = [it for it in items if it["_id"] not in state]
    done = sorted((it for it in items if it["_id"] in state), key=lambda x: x["_when"], reverse=True)[:30]
    out = ["# План Instagram @marmar.candles", "",
           "_Страница обновляется сама при каждом запуске планировщика и сразу после слияния поста._", ""]
    waiting = [p for p in prs if any(f.get("path", "").startswith("social/instagram/queue/") for f in p.get("files", []))]
    out += ["## 🙋 Ждут твоего подтверждения", ""]
    out += [f"- [{p['title']}]({p['url']}): открой, посмотри и нажми Merge" for p in waiting] or ["Ничего не ждёт."]
    out += ["", "## ⏳ Запланировано", ""]
    out += [head] + [row(it, None) for it in upcoming] if upcoming else ["Пока ничего не запланировано."]
    out += ["", "## ✅ Уже вышло", ""]
    out += [head] + [row(it, state[it["_id"]]) for it in done] if done else ["Пока ничего не опубликовано."]
    return "\n".join(out) + "\n"


def main() -> None:
    prs = []
    if os.environ.get("PRS_JSON") and os.path.exists(os.environ["PRS_JSON"]):
        try:
            with open(os.environ["PRS_JSON"], encoding="utf-8") as f:
                prs = json.load(f)
        except ValueError:  # gh failed: the page still renders, just without the waiting list
            prs = []
    text = render(load_queue(QUEUE_GLOB), load_state(STATE_PATH), prs, datetime.now(timezone.utc))
    with open(PLAN_PATH, "w", encoding="utf-8") as f:
        f.write(text)
    print(f"wrote {PLAN_PATH}")


if __name__ == "__main__":
    main()
