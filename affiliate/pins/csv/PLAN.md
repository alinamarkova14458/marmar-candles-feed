# Pinterest CSV plan, 19.11–18.12.2026 (jalina.art)

Two files for Pinterest bulk create (Settings → Import content → Upload .csv), same columns as the test file
`jalina-test-2026-11-17.csv` that Alina already uploaded successfully:

| File | Pins | Dates | Upload |
|---|---|---|---|
| `jalina-2026-11-19_12-02.csv` | 133 | 19.11–02.12 | 18.11, right after the test pins are confirmed |
| `jalina-2026-12-03_12-18.csv` | 179 | 03.12–18.12 | any day 25.11–02.12 (needs the last date ≤30 days ahead) |

**Pinterest limits (help.pinterest.com, checked 15.11.2026):** bulk upload takes up to **200 pins per .csv**
("Bulk upload Pins"); the scheduler allows publishing **up to 30 days in advance** ("Schedule Pins"; the bulk page itself
gives no horizon, so we keep every file inside 30 days). The same "Schedule Pins" page says the native scheduler holds
at most 10 future Pins; it is unclear whether that also applies to CSV uploads. After uploading file 1, check that all
133 scheduled pins appear on the profile; if only some do, tell Claude and the files will be split into smaller ones.

**Rules applied** (checked by `check_csv.py`): jalina.art pins/day incl. Metricool: 19.11 ≤3, 20–22.11 ≤5, from 23.11 ≤8;
Payhip pins of own sets 3–4/day (board "Watercolor Birthday & Holiday Clipart", `utm_campaign=own-payhip`);
total ≤15/day; 20-min slots 15:00–23:00 Riga = 13:00–21:00 UTC (Publish date is UTC), no slot shared with Metricool;
each jalina.art URL ≤1/day and ≤3 per 7 days (two days in a row only as a last resort); each image ≥4 days apart;
unique descriptions; no links to redirected slugs; Christmas posts until 15.12, Hanukkah until 10.12 (Hanukkah 2026:
4–12 Dec), New Year / winter from late Nov, evergreen guides, tools and fonts throughout.

**Images:** 100 JPGs in `jalina-blog/public/pins/csv/` (https://jalina.art/pins/csv/…): article pins `art-<slug>-A|B|C.jpg`
from `v2/article.py` (presets + `v2/specs/*.json`), own sets `own-<set>-v1..v4.jpg` (Payhip) and `-v5` (/shop/ pages)
from `v2/gen.py R <set> --variant N`. Rebuild: `python3 build_csv.py && python3 check_csv.py jalina-2026-*.csv --live`.

**Known conflicts in Metricool (not changed by this plan):** 19.11 already has 8 jalina.art pins (ramp says ≤3) and
21.11 has 6 (≤5), one of them to `/candy-cane-clipart/`, which now 301-redirects to `/christmas-food-clipart/`.
The CSV adds no jalina.art pins on 19, 21 and 22.11. To follow the ramp, move 5 pins on 19.11 and 1 on 21.11 to draft
(e.g. the candy-cane one).

## Per day
Built by `build_csv.py`, checked by `check_csv.py`. Times in the CSV are UTC (Riga = UTC+2). "Metricool" = Pinterest pins already scheduled there (read 15.11.2026, non-draft).

| Date | Metricool | CSV jalina.art | CSV Payhip | Total | jalina.art URLs in CSV (Riga time) |
|---|---|---|---|---|---|
| 19.11 | 8 | 0 | 4 | 12 | — |
| 20.11 | 0 | 5 | 3 | 8 | 15:00 `/hanukkah-clipart/`, 16:00 `/clipart-blurry-when-printed/`, 18:20 `/free-fonts-for-commercial-use/`, 19:40 `/free-christmas-fonts/`, 22:00 `/christmas-gift-tags-printable/` |
| 21.11 | 6 | 0 | 4 | 10 | — |
| 22.11 | 5 | 0 | 3 | 8 | — |
| 23.11 | 0 | 8 | 4 | 12 | 15:00 `/christmas-food-clipart/`, 15:40 `/christmas-fonts-for-canva/`, 17:20 `/tools/print-size-calculator/`, 18:00 `/png-not-transparent/`, 19:20 `/tools/png-transparency-checker/`, 20:00 `/christmas-digital-paper/`, 21:40 `/canva-font-pairings/`, 22:20 `/shop/pastel-christmas/` |
| 24.11 | 1 | 7 | 3 | 11 | 15:20 `/my-art-sold-on-temu/`, 16:20 `/christmas-tumbler-wrap/`, 18:00 `/shop/cozy-winter-wonderland/`, 18:40 `/coquette-christmas-clipart/`, 20:20 `/watercolor-christmas-clipart/`, 21:20 `/christmas-stocking-name-fonts/`, 23:00 `/hanukkah-clipart/` |
| 25.11 | 0 | 8 | 4 | 12 | 15:00 `/winter-wonderland-clipart/`, 15:40 `/free-christmas-fonts/`, 17:20 `/free-fonts-for-commercial-use/`, 18:00 `/clipart-blurry-when-printed/`, 19:20 `/christmas-gift-tags-printable/`, 20:00 `/christmas-card-fonts/`, 21:40 `/christmas-planner-stickers/`, 22:20 `/png-not-transparent/` |
| 26.11 | 0 | 8 | 3 | 11 | 15:00 `/shop/vintage-christmas/`, 15:40 `/christmas-food-clipart/`, 17:20 `/tools/png-transparency-checker/`, 18:20 `/tools/print-size-calculator/`, 19:40 `/christmas-fonts-for-canva/`, 20:40 `/christmas-digital-paper/`, 22:20 `/canva-font-pairings/`, 23:00 `/hanukkah-clipart/` |
| 27.11 | 0 | 8 | 4 | 12 | 15:00 `/new-year-fonts/`, 15:40 `/new-years-eve-clipart/`, 17:20 `/watercolor-christmas-clipart/`, 18:00 `/my-art-sold-on-temu/`, 19:20 `/christmas-gift-tags-printable/`, 20:00 `/png-not-transparent/`, 21:40 `/clipart-blurry-when-printed/`, 22:20 `/coquette-christmas-clipart/` |
| 28.11 | 0 | 8 | 3 | 11 | 15:00 `/shop/new-years-eve/`, 15:40 `/free-fonts-for-commercial-use/`, 17:20 `/free-christmas-fonts/`, 18:20 `/christmas-tumbler-wrap/`, 19:40 `/christmas-stocking-name-fonts/`, 20:40 `/christmas-card-fonts/`, 22:20 `/christmas-planner-stickers/`, 23:00 `/hanukkah-clipart/` |
| 29.11 | 0 | 8 | 4 | 12 | 15:00 `/tools/png-transparency-checker/`, 15:40 `/christmas-food-clipart/`, 17:20 `/tools/print-size-calculator/`, 18:00 `/christmas-fonts-for-canva/`, 19:20 `/winter-wonderland-clipart/`, 20:00 `/christmas-digital-paper/`, 21:40 `/canva-font-pairings/`, 22:20 `/christmas-gift-tags-printable/` |
| 30.11 | 0 | 8 | 3 | 11 | 15:00 `/shop/pastel-christmas/`, 15:40 `/png-not-transparent/`, 17:20 `/clipart-blurry-when-printed/`, 18:20 `/free-christmas-fonts/`, 19:40 `/watercolor-christmas-clipart/`, 20:40 `/free-fonts-for-commercial-use/`, 22:20 `/coquette-christmas-clipart/`, 23:00 `/my-art-sold-on-temu/` |
| 01.12 | 0 | 8 | 4 | 12 | 15:00 `/shop/cozy-winter-wonderland/`, 15:40 `/new-year-fonts/`, 17:20 `/new-years-eve-clipart/`, 18:00 `/winter-animal-clipart/`, 19:20 `/hanukkah-clipart/`, 20:00 `/christmas-card-fonts/`, 21:40 `/christmas-planner-stickers/`, 22:20 `/christmas-tumbler-wrap/` |
| 02.12 | 0 | 8 | 3 | 11 | 15:00 `/tools/print-size-calculator/`, 15:40 `/christmas-fonts-for-canva/`, 17:20 `/christmas-gift-tags-printable/`, 18:20 `/winter-wonderland-clipart/`, 19:40 `/christmas-food-clipart/`, 20:40 `/tools/png-transparency-checker/`, 22:20 `/christmas-stocking-name-fonts/`, 23:00 `/christmas-digital-paper/` |
| 03.12 | 0 | 8 | 4 | 12 | 15:00 `/shop/vintage-christmas/`, 15:40 `/canva-font-pairings/`, 17:20 `/free-fonts-for-commercial-use/`, 18:00 `/clipart-blurry-when-printed/`, 19:20 `/png-not-transparent/`, 20:00 `/free-christmas-fonts/`, 21:40 `/hanukkah-clipart/`, 22:20 `/new-year-fonts/` |
| 04.12 | 0 | 8 | 3 | 11 | 15:00 `/new-years-eve-clipart/`, 15:40 `/my-art-sold-on-temu/`, 17:20 `/coquette-christmas-clipart/`, 18:20 `/watercolor-christmas-clipart/`, 19:40 `/christmas-planner-stickers/`, 20:40 `/christmas-card-fonts/`, 22:20 `/christmas-fonts-for-canva/`, 23:00 `/tools/print-size-calculator/` |
| 05.12 | 0 | 8 | 4 | 12 | 15:00 `/shop/new-years-eve/`, 15:40 `/christmas-gift-tags-printable/`, 17:20 `/winter-wonderland-clipart/`, 18:00 `/tools/png-transparency-checker/`, 19:20 `/christmas-food-clipart/`, 20:00 `/christmas-tumbler-wrap/`, 21:40 `/christmas-digital-paper/`, 22:20 `/christmas-stocking-name-fonts/` |
| 06.12 | 0 | 8 | 3 | 11 | 15:00 `/new-year-fonts/`, 15:40 `/free-christmas-fonts/`, 17:20 `/png-not-transparent/`, 18:20 `/free-fonts-for-commercial-use/`, 19:40 `/clipart-blurry-when-printed/`, 20:40 `/canva-font-pairings/`, 22:20 `/winter-animal-clipart/`, 23:00 `/tools/print-size-calculator/` |
| 07.12 | 0 | 8 | 4 | 12 | 15:00 `/shop/pastel-christmas/`, 15:40 `/new-years-eve-clipart/`, 17:20 `/christmas-fonts-for-canva/`, 18:00 `/christmas-card-fonts/`, 19:20 `/christmas-planner-stickers/`, 20:00 `/winter-wonderland-clipart/`, 21:40 `/christmas-gift-tags-printable/`, 22:20 `/my-art-sold-on-temu/` |
| 08.12 | 0 | 8 | 3 | 11 | 15:00 `/shop/cozy-winter-wonderland/`, 15:40 `/lunar-new-year-clipart/`, 17:20 `/tools/png-transparency-checker/`, 18:20 `/christmas-food-clipart/`, 19:40 `/watercolor-christmas-clipart/`, 20:40 `/coquette-christmas-clipart/`, 22:20 `/christmas-digital-paper/`, 23:00 `/hanukkah-clipart/` |
| 09.12 | 0 | 8 | 4 | 12 | 15:00 `/free-fonts-for-commercial-use/`, 15:40 `/new-year-fonts/`, 17:20 `/free-christmas-fonts/`, 18:00 `/clipart-blurry-when-printed/`, 19:20 `/png-not-transparent/`, 20:00 `/tools/print-size-calculator/`, 21:40 `/christmas-tumbler-wrap/`, 22:20 `/christmas-stocking-name-fonts/` |
| 10.12 | 0 | 8 | 3 | 11 | 15:00 `/shop/vintage-christmas/`, 15:40 `/canva-font-pairings/`, 17:20 `/christmas-gift-tags-printable/`, 18:20 `/winter-wonderland-clipart/`, 19:40 `/christmas-fonts-for-canva/`, 20:40 `/new-years-eve-clipart/`, 22:20 `/christmas-card-fonts/`, 23:00 `/christmas-planner-stickers/` |
| 11.12 | 0 | 8 | 4 | 12 | 15:00 `/christmas-food-clipart/`, 15:40 `/tools/png-transparency-checker/`, 17:20 `/my-art-sold-on-temu/`, 18:00 `/winter-animal-clipart/`, 19:20 `/christmas-digital-paper/`, 20:00 `/tools/print-size-calculator/`, 21:40 `/watercolor-christmas-clipart/`, 22:20 `/new-year-fonts/` |
| 12.12 | 0 | 8 | 3 | 11 | 15:00 `/shop/new-years-eve/`, 15:40 `/png-not-transparent/`, 17:20 `/free-fonts-for-commercial-use/`, 18:20 `/clipart-blurry-when-printed/`, 19:40 `/free-christmas-fonts/`, 20:40 `/coquette-christmas-clipart/`, 22:20 `/christmas-gift-tags-printable/`, 23:00 `/winter-wonderland-clipart/` |
| 13.12 | 0 | 8 | 4 | 12 | 15:00 `/new-years-eve-clipart/`, 15:40 `/christmas-fonts-for-canva/`, 17:20 `/christmas-tumbler-wrap/`, 18:00 `/christmas-stocking-name-fonts/`, 19:20 `/canva-font-pairings/`, 20:00 `/christmas-planner-stickers/`, 21:40 `/christmas-card-fonts/`, 22:20 `/new-year-fonts/` |
| 14.12 | 0 | 8 | 3 | 11 | 15:00 `/tools/print-size-calculator/`, 15:40 `/tools/png-transparency-checker/`, 17:20 `/christmas-food-clipart/`, 18:20 `/christmas-digital-paper/`, 19:40 `/lunar-new-year-clipart/`, 20:40 `/free-fonts-for-commercial-use/`, 22:20 `/winter-wonderland-clipart/`, 23:00 `/clipart-blurry-when-printed/` |
| 15.12 | 0 | 8 | 4 | 12 | 15:00 `/shop/cozy-winter-wonderland/`, 15:40 `/christmas-gift-tags-printable/`, 17:20 `/free-christmas-fonts/`, 18:00 `/png-not-transparent/`, 19:20 `/my-art-sold-on-temu/`, 20:00 `/watercolor-christmas-clipart/`, 21:40 `/christmas-fonts-for-canva/`, 22:20 `/new-years-eve-clipart/` |
| 16.12 | 0 | 8 | 3 | 11 | 15:00 `/new-year-fonts/`, 15:40 `/winter-animal-clipart/`, 17:20 `/canva-font-pairings/`, 18:20 `/tools/print-size-calculator/`, 19:40 `/tools/png-transparency-checker/`, 20:40 `/clipart-blurry-when-printed/`, 22:20 `/free-fonts-for-commercial-use/`, 23:00 `/lunar-new-year-clipart/` |
| 17.12 | 0 | 7 | 3 | 10 | 15:00 `/winter-wonderland-clipart/`, 16:00 `/new-years-eve-clipart/`, 17:40 `/png-not-transparent/`, 18:40 `/my-art-sold-on-temu/`, 20:20 `/canva-font-pairings/`, 21:20 `/winter-animal-clipart/`, 23:00 `/lunar-new-year-clipart/` |
| 18.12 | 0 | 5 | 3 | 8 | 15:00 `/new-year-fonts/`, 16:00 `/tools/png-transparency-checker/`, 18:20 `/tools/print-size-calculator/`, 19:40 `/my-art-sold-on-temu/`, 22:00 `/winter-animal-clipart/` |

Payhip pins per set: 05-pastel-christmas ×16, 14-moody-florals ×16, 15-vintage-christmas ×15, 17-cozy-winter-wonderland ×18, 25-new-years-eve ×22, 26-hanukkah ×17

jalina.art pins per URL: `/tools/print-size-calculator/` ×11, `/tools/png-transparency-checker/` ×10, `/clipart-blurry-when-printed/` ×10, `/free-fonts-for-commercial-use/` ×10, `/christmas-gift-tags-printable/` ×10, `/png-not-transparent/` ×10, `/canva-font-pairings/` ×9, `/winter-wonderland-clipart/` ×9, `/my-art-sold-on-temu/` ×9, `/christmas-fonts-for-canva/` ×9, `/free-christmas-fonts/` ×9, `/new-year-fonts/` ×9, `/new-years-eve-clipart/` ×8, `/christmas-food-clipart/` ×8, `/christmas-digital-paper/` ×8, `/christmas-planner-stickers/` ×7, `/christmas-card-fonts/` ×7, `/hanukkah-clipart/` ×7, `/watercolor-christmas-clipart/` ×7, `/christmas-stocking-name-fonts/` ×6, `/winter-animal-clipart/` ×6, `/christmas-tumbler-wrap/` ×6, `/coquette-christmas-clipart/` ×6, `/lunar-new-year-clipart/` ×4, `/shop/cozy-winter-wonderland/` ×4, `/shop/new-years-eve/` ×3, `/shop/pastel-christmas/` ×3, `/shop/vintage-christmas/` ×3

## Payhip-only version (Alina, 19.11.2026)
Pinterest blocks every jalina.art link ("may lead to spam") since 10.11. All Metricool pins with jalina.art links for 19.11–02.12 (25) were moved to draft on 19.11.
New files (built with `PAYHIP_ONLY=1 python3 build_csv.py`, checked with `PAYHIP_ONLY=1 python3 check_csv.py`):
- `jalina-payhip-2026-11-20_12-02.csv` — 44 pins, 20.11–02.12, 2–4/day
- `jalina-payhip-2026-12-03_12-18.csv` — 46 pins, 03.12–18.12, 2–4/day
Only own sets → payhip.com/b/<code> (utm_campaign=own-payhip), board "Watercolor Birthday & Holiday Clipart"; 6 in-season sets; one link ≤1/day and ≤4 per 7 days. Images stay on jalina.art/pins/csv/ (images from jalina.art pass; the test pins on 17–18.11 worked).
The original files with jalina.art article pins are kept for after the block is lifted (dates will need rebuilding).
