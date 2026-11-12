"""Dark-theme background for template R: wine-to-black wet-on-wet watercolor wash -> assets/wine-wash.jpg (1000x1500).

Chosen by Alina (Nov 12 2026, council variant "d3/pin-dark-b"). Soft multi-scale noise drives a wash from near-black
into wine and plum, with thin brighter pigment-bloom rims and a gentle central glow. The grain is the high-passed real
CC0 paper scan `assets/paper-1.jpg` (ambientCG Paper001). Deterministic (fixed seeds).
  python3 dark_wash.py
"""
import os
import numpy as np
from PIL import Image, ImageFilter

HERE = os.path.dirname(os.path.abspath(__file__))
W, H = 1000, 1500


def noise(scale, seed):
    rng = np.random.default_rng(seed)
    s = rng.random((H // scale + 2, W // scale + 2)).astype(np.float32)
    return np.asarray(Image.fromarray((s * 255).astype(np.uint8)).resize((W, H), Image.BICUBIC)).astype(np.float32) / 255


def main():
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    p = np.asarray(Image.open(os.path.join(HERE, 'assets', 'paper-1.jpg')).convert('L').crop((0, 0, W, H))).astype(np.float32)
    blur = np.asarray(Image.fromarray(p.astype(np.uint8)).filter(ImageFilter.GaussianBlur(12))).astype(np.float32)
    grain = (p - blur) / 255.0
    n = 0.5 * noise(380, 11) + 0.35 * noise(170, 12) + 0.15 * noise(60, 13)
    n2 = 0.7 * noise(500, 21) + 0.3 * noise(160, 22)
    dark = np.array([14, 5, 8], np.float32); wine = np.array([74, 14, 28], np.float32); plum = np.array([52, 14, 34], np.float32)
    v = np.clip((n - 0.32) * 2.0, 0, 1) ** 1.2 * 0.85 + 0.15
    d = np.sqrt(((xx - 500) / 700) ** 2 + ((yy - 700) / 900) ** 2)
    vig = np.clip(1 - d * 0.95, 0, 1)
    B = dark + (wine - dark) * (v * vig)[..., None]
    B = B + (plum - dark) * (np.clip((n2 - .5) * 2, 0, 1) * 0.6 * vig)[..., None]
    edge = np.exp(-((n - 0.55) / 0.008) ** 2) * 0.32  # pigment bloom rims
    B = B + np.array([40, 10, 18], np.float32) * (edge * vig)[..., None]
    B = B + grain[..., None] * 110
    B = Image.fromarray(B.clip(0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(0.7))
    out = os.path.join(HERE, 'assets', 'wine-wash.jpg')
    B.save(out, quality=90, optimize=True)
    print(out, os.path.getsize(out) // 1024, 'KB')


if __name__ == '__main__':
    main()
