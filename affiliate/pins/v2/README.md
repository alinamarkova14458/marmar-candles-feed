# Pin templates v2 (jalina.art, 1000x1500)

Built on the Nov 2026 top-pin research (62% product only, 59% no text, 65% light backgrounds,
0% prices, numbers only inside a short headline) and Pinterest's creative guidance (brand clearly visible
but not overpowering, product front and center / shown in use, key message on top, 2:3).

**Brand plaque (all templates, Nov 12 2026):** `jalina.art` in DM Serif 42 px on a soft white pill,
bottom centre (`.brand` in `gen.py`) — readable at feed size (236 px wide ≈ 10 px text), never covers
the product. Layouts keep ~130 px free at the bottom for it.

| Template | Look | Use for |
|---|---|---|
| **A** set sheet | cream background, headline "`<count> <set name> PNG`" (number in cherry), 12 elements of the set in justified rows (never cropped), brand plaque | own clipart sets → `/shop/<slug>/` or Payhip |
| **B** one hero | one big element on a light transparency checkerboard (or a post's product image on white), 0–3 words, brand plaque | own sets, single-product pins |
| **C** numbered list | blush background, headline "`<N> <topic>`", 2×4 grid of the post's numbered product images (max 8 shown, N = real item count), brand plaque | blog articles |
| **D** in use | cream background, headline "`<count> <set name> PNG`", one real mockup of the set (`jalina-factory/ready/<set>/mockup-N.jpg`, shown whole in a rounded card), strip of 3 set elements under it, brand plaque. No price, no dark blocks | own sets → `/shop/<slug>/` or Payhip |

## Usage
```bash
export PINS_CACHE=/path/to/scratch   # optional; set ZIPs are unpacked+trimmed+downscaled here once
python3 gen.py list 21-dino-birthday         # element indices (lettering PNGs marked)
python3 gen.py A 19-elegant-wedding          # -> v2-A-19-elegant-wedding.html  (-n 9..20 elements)
python3 gen.py B 21-dino-birthday 3          # element #3 as hero (--text "" for no text)
python3 gen.py B post:christmas-tumbler-wrap 2   # 2nd product image of a post, white bg
python3 gen.py C christmas-tumbler-wrap      # list pin (--title "8 Christmas Tumbler Wraps" to override)
python3 gen.py D 21-dino-birthday 2          # mockup-2 as hero -> v2-D-21-dino-birthday-m2.html
                                             # --title "Make your own dino party toppers" --strip 0|3|4 --kicker "..."
node shot.js [prefix]                        # renders *.html -> out/*.jpg
```
- Headlines for A/B come from `jalina-blog/src/shop.json` (`name`, `count`); C counts the post's
  `## N. Title` sections that have an image. Keep overrides true (number = real count), no prices.
- A picks elements automatically: skips lettering/text PNGs and very wide garlands, spreads the
  pick over the set. B without index takes the first square-ish non-lettering element.
- D mockups (1200×800, check visually per set): 1 sticker sheet, 2 toppers + gift tags, 3 tote bag,
  4 t-shirt + baby bodysuit. They already carry their own use label + "made with" badge, so the pin adds
  only the headline (optional Caveat `--kicker`). A use-case `--title` must stay true to the mockup and
  shop.json/LISTING.md.
- Light/ivory elements: prefer A (cream) over B (checkerboard) if they get lost.
- `samples/` — reference renders. `out/`, `*.html` are not committed (HTML points to the local cache).
