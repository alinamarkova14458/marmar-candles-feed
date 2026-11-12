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

**S** product sheet (Nov 12 2026, after Alina's feedback and her favourite reference "Coffee" by a watercolor studio):
few BIG elements in a loose 3-column grid: 9 by default (3x3), 12 on 1000x2000 (`-tall`), `--n 6..12`. The middle column
sits slightly lower, like a hand-laid sheet. Picks the most recognisable elements of the set: no lettering, no garlands,
no tiny scatters (confetti, petals, footprints, clusters); solid, dense objects come first, with one per subject type
(gift, tree/plant, ornament, cake, drink, vehicle, house...) so the sheet stays varied.
Background: procedural cold-press cotton watercolor paper, `assets/cotton-paper.jpg`, made by `paper.py`. It uses
multi-octave pebble height map, Lambert light from the upper left, cool #F4F2EE, even light and no downloads. Elements use `multiply` so the paper
shows through like print. Header: studio line `JALINA.ART` (Manrope spaced caps, muted), set name big in DM Serif
Display, `<count> PNG · 300 DPI` (count from shop.json). No bottom brand: the studio line carries jalina.art.

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
python3 gen.py S 05-pastel-christmas --size 1000x2000   # -> v2-S-05-pastel-christmas-tall.html (--n 6..12, --title)
python3 paper.py                             # regenerate assets/cotton-paper.jpg (--seed)
node shot.js [prefix]                        # renders *.html -> out/*.jpg (size from <html data-w data-h>, default 1000x1500)
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
