"""Build the 3 pin backgrounds (1000x2000 JPG) from REAL CC0 paper scans (ambientCG, see assets/LICENSES.md).

  python3 paper_real.py <Paper001_2K-JPG_Color.jpg> <Paper006_2K-JPG_Color.jpg>

Each texture is flattened (large-scale shading / vignette removed with a wide blur high-pass), its fine
detail is kept at a chosen strength and laid over an even near-white tone:
  paper-1.jpg  cold-press watercolor paper (ambientCG Paper001), near-white, soft visible grain
  paper-2.jpg  smooth warm cotton / off-white (Paper006 fibres, very low contrast, desaturated)
  paper-3.jpg  creamy handmade paper: Paper001 tooth + Paper006 fibres, light cream
Paper001 (2048x1201) is used rotated 90 deg so one scan covers 1000x2000 without tiling.
"""
import os, sys
import numpy as np
from PIL import Image, ImageFilter

W, H = 1000, 2000
HERE = os.path.dirname(os.path.abspath(__file__))


def detail(path, rotate=False, scale=1.0):
    """Luminance detail (mean 0) of a scan, cropped to W x H, without its large-scale light falloff."""
    im = Image.open(path).convert('L')
    if rotate:
        im = im.transpose(Image.Transpose.ROTATE_90)
    if scale != 1.0:
        im = im.resize((round(im.width * scale), round(im.height * scale)), Image.LANCZOS)
    x0, y0 = (im.width - W) // 2, (im.height - H) // 2
    im = im.crop((x0, y0, x0 + W, y0 + H))
    a = np.asarray(im, float)
    low = np.asarray(im.filter(ImageFilter.GaussianBlur(60)), float)
    d = a - low
    return d / (d.std() + 1e-9)


def save(name, base, d, amp):
    rgb = np.array(base, float)[None, None, :] + (d * amp)[..., None]
    out = os.path.join(HERE, 'assets', name)
    Image.fromarray(np.clip(rgb, 0, 255).astype(np.uint8)).save(out, quality=86, optimize=True)
    print(out, os.path.getsize(out) // 1024, 'KB')


if __name__ == '__main__':
    p1, p6 = sys.argv[1], sys.argv[2]
    d1 = detail(p1, rotate=True)
    d6 = detail(p6, scale=1.0)
    save('paper-1.jpg', (246, 245, 242), d1, 4.6)                    # cold-press, near-white
    save('paper-2.jpg', (248, 245, 240), d6, 1.6)                    # smooth warm cotton
    save('paper-3.jpg', (247, 240, 228), 0.75 * d1 + 0.75 * d6, 3.4)  # creamy handmade
