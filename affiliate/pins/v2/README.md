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
few BIG elements (9 by default, 12 on 1000x2000 `-tall`, `--n 6..12`) in an organic curated composition rather than a grid.
- **Hero:** 1 element, about 1.85x the base size, placed centre or upper-middle. It is the most iconic one: bouquet > cake > house > wreath > tree > truck.
- **Mediums:** 2–3 naturally large items at 1.3x. Small things (mittens, hats, ornaments, butterflies) get 0.86x. Largest to smallest is capped at 2.3x.
- **Placement:** each element goes to the sampled spot with the most clearance, then the layout is relaxed. Elements are re-seated to even out whitespace, measured to the real paint (alpha), not bounding boxes. Finally they grow by up to 30% where there is room.
- **Seeds:** 16 seeds are tried; the winner has the biggest elements, the smallest empty holes and a balanced mass.
- **Guarantees:** bounding boxes never overlap, and nothing is cropped or touches the header.

The picks are the most recognisable elements of the set. No lettering, no garlands, no tiny scatters (confetti, petals, footprints, clusters). Solid, dense objects come first, with one per subject type so the sheet stays varied.
Background: real CC0 paper scans from ambientCG (`assets/paper-1..3.jpg`, built by `paper_real.py`; sources and licence are in
`assets/LICENSES.md`). `--paper 1` (the default) is cold-press watercolor paper, near-white. `2` is smooth warm cotton, very subtle. `3` is creamy
handmade paper with faint fibres. Elements use `multiply` so the paper
shows through like print. Header: studio line `JALINA.ART` (Manrope spaced caps, muted), set name big in DM Serif
Display, `<count> PNG · 300 DPI` (count from shop.json). No bottom brand: the studio line carries jalina.art.

**R** replica (Nov 12 2026): a one-to-one copy of Alina's favourite reference pin "Coffee" (Crystal Sea Studio), with the content swapped for our set.
- **Measured layout:** studio caps y≈40–67, title cap y≈97–156, a 3×3 grid of 9 items. The middle column sits ~75 px lower in every row, and the centre of row 2 has a small caps line (`<count> PNG · 300 DPI`) with an item under it. Items fill ~325×350 by their own shape; outer items sit 14–40 px from the edges.
- **Fonts:** Bodoni Moda (OFL, `assets/fonts/`) in slate #7D8CAB for the studio line `JALINA.ART` and the middle line, #3C3C3C for the set name in Title Case.
- **Paper:** `assets/paper-r.jpg`, the relief of the CC0 Paper001 scan.
- `samples/cmp-R-*.jpg` shows the reference and ours side by side.

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
python3 paper_real.py <Paper001 Color.jpg> <Paper006 Color.jpg>   # rebuild assets/paper-1..3.jpg (see assets/LICENSES.md)
python3 gen.py R 14-moody-florals            # -> v2-R-14-moody-florals.html (--title to override)
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
