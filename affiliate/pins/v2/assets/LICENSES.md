# Background textures — sources and licence

All three backgrounds are built by `paper_real.py` from real paper scans by **ambientCG** (https://ambientcg.com).
**Licence: CC0 1.0 Universal (public domain).** Free for commercial use, no attribution required
(https://docs.ambientcg.com/license/). Only the Color (albedo) maps were used. Downloaded on 12.11.2026.

| File | Source asset | Download | Processing |
|---|---|---|---|
| `paper-1.jpg` (cold-press watercolor, near-white) | Paper 001: https://ambientcg.com/view?id=Paper001 | https://ambientcg.com/get?file=Paper001_2K-JPG.zip (`Paper001_2K-JPG_Color.jpg`) | rotated 90°, centre crop 1000x2000, large-scale shading removed, grain ×4.6 over #F6F5F2 |
| `paper-2.jpg` (smooth warm cotton, very subtle) | Paper 006: https://ambientcg.com/view?id=Paper006 | https://ambientcg.com/get?file=Paper006_2K-JPG.zip (`Paper006_2K-JPG_Color.jpg`) | luminance only (kraft colour dropped), centre crop, fibre detail ×1.6 over #F8F5F0 |
| `paper-3.jpg` (creamy handmade) | Paper 001 + Paper 006 (as above) | as above | both details mixed, ×3.4 over cream #F7F0E4 |

No tiling: each texture covers 1000x2000 from a single scan (no seams). Source ZIPs are not committed.

## Template R paper (`paper-r.jpg`)
Built by `paper_real.py` (third argument) from the **height map** of the same CC0 asset, Paper 001 (https://ambientcg.com/view?id=Paper001):
`Paper001_2K-JPG_Displacement.jpg` from https://ambientcg.com/get?file=Paper001_2K-JPG.zip, enlarged 2.0×/2.8× to the grain size of
Alina's reference pin, lit from the left (Lambert), near-white #F8F8F6, even. Licence: CC0 1.0 (as above).

## Fonts (`fonts/`)
- **Bodoni Moda** (variable, opsz + wght), `BodoniModa-VariableFont_opsz,wght.ttf`. Copyright 2020 The Bodoni Moda Project Authors.
  **SIL Open Font License 1.1**, full text in `fonts/OFL-BodoniModa.txt`, so commercial use and embedding in images are allowed.
  Source: https://github.com/google/fonts/tree/main/ofl/bodonimoda (downloaded 12.11.2026).
