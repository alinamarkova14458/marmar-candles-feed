# Pin templates v2 (jalina.art, 1000x1500)

Built on the Nov 2026 top-pin research (62% product only, 59% no text, 65% light backgrounds,
0% prices, numbers only inside a short headline, brand = small URL at the bottom).

| Template | Look | Use for |
|---|---|---|
| **A** set sheet | cream background, headline "`<count> <set name> PNG`" (number in cherry), 12 elements of the set in justified rows (never cropped), tiny `jalina.art` | own clipart sets → `/shop/<slug>/` or Payhip |
| **B** one hero | one big element on a light transparency checkerboard (or a post's product image on white), 0–3 words, tiny `jalina.art` | own sets, single-product pins |
| **C** numbered list | blush background, headline "`<N> <topic>`", 2×4 grid of the post's numbered product images (max 8 shown, N = real item count), tiny `jalina.art` | blog articles |

## Usage
```bash
export PINS_CACHE=/path/to/scratch   # optional; set ZIPs are unpacked+trimmed+downscaled here once
python3 gen.py list 21-dino-birthday         # element indices (lettering PNGs marked)
python3 gen.py A 19-elegant-wedding          # -> v2-A-19-elegant-wedding.html  (-n 9..20 elements)
python3 gen.py B 21-dino-birthday 3          # element #3 as hero (--text "" for no text)
python3 gen.py B post:christmas-tumbler-wrap 2   # 2nd product image of a post, white bg
python3 gen.py C christmas-tumbler-wrap      # list pin (--title "8 Christmas Tumbler Wraps" to override)
node shot.js [prefix]                        # renders *.html -> out/*.jpg
```
- Headlines for A/B come from `jalina-blog/src/shop.json` (`name`, `count`); C counts the post's
  `## N. Title` sections that have an image. Keep overrides true (number = real count), no prices.
- A picks elements automatically: skips lettering/text PNGs and very wide garlands, spreads the
  pick over the set. B without index takes the first square-ish non-lettering element.
- Light/ivory elements: prefer A (cream) over B (checkerboard) if they get lost.
- `samples/` — reference renders. `out/`, `*.html` are not committed (HTML points to the local cache).
