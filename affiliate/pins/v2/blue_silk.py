"""Blue theme background for template R: dusty blue silk -> assets/blue-silk.jpg (1000x1500).

Chosen by Alina (Nov 12 2026, council variant "wed-3") for white/ivory sets, which would vanish on white paper.
Diagonal soft folds (two sine waves warped by smooth noise) give the silk sheen; a gentle central light; fine grain
from the high-passed real CC0 paper scan `assets/paper-1.jpg` (ambientCG Paper001). Deterministic.
  python3 blue_silk.py
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
    grain = (p - np.asarray(Image.fromarray(p.astype(np.uint8)).filter(ImageFilter.GaussianBlur(10))).astype(np.float32)) / 255
    u = (xx * 0.8 + yy * 0.6) / 1000
    warp = noise(500, 41) * 1.1 + noise(250, 42) * 0.3
    folds = np.sin((u * 3.4 + warp) * np.pi) * 0.55 + np.sin((u * 7 + warp * 1.3) * np.pi) * 0.12
    sheen = np.clip(folds, 0, 1) ** 2.2
    base = np.array([104, 124, 142], np.float32); hi = np.array([170, 186, 198], np.float32); sh = np.array([70, 86, 104], np.float32)
    L = np.clip(1 - np.sqrt(((xx - 500) / 1000) ** 2 + ((yy - 700) / 1100) ** 2), 0, 1)
    A = base + (hi - base) * (sheen * 0.45 * (0.5 + 0.5 * L))[..., None] + (sh - base) * (np.clip(-folds, 0, 1) * 0.32)[..., None]
    A = np.asarray(Image.fromarray(A.clip(0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(5))).astype(np.float32) + grain[..., None] * 14
    out = os.path.join(HERE, 'assets', 'blue-silk.jpg')
    Image.fromarray(A.clip(0, 255).astype(np.uint8)).save(out, quality=90, optimize=True)
    print(out, os.path.getsize(out) // 1024, 'KB')


if __name__ == '__main__':
    main()
