"""Procedural cold-press cotton watercolor paper -> assets/cotton-paper.jpg (1000x2000, no downloads).

Height map = blurred noise at several scales (fine grain, the rounded "tooth" bumps of cold-press
paper, soft hills) + a few faint cotton fibres; lit from the top-left (emboss shading) and laid over
a warm white with slow tonal unevenness and a very faint deckle-like darkening at the edges.
  python3 paper.py [--seed 7] [--out assets/cotton-paper.jpg]
"""
import argparse, os
import numpy as np
from PIL import Image

W, H = 1000, 2000
BASE = np.array([250, 247, 241], float)  # warm white (#FAF7F1)


def blur(a, sigma):
    """Gaussian blur via FFT (periodic), no scipy needed."""
    fy = np.fft.fftfreq(a.shape[0])[:, None]
    fx = np.fft.fftfreq(a.shape[1])[None, :]
    g = np.exp(-2 * (np.pi * sigma) ** 2 * (fx ** 2 + fy ** 2))
    return np.real(np.fft.ifft2(np.fft.fft2(a) * g))


def norm(a):
    return (a - a.mean()) / (a.std() + 1e-9)


def make(seed):
    rng = np.random.default_rng(seed)
    n = lambda: rng.standard_normal((H, W))
    # tooth: rounded bumps ~5-10 px, softly clipped so they read as raised grains, not static
    tooth = norm(blur(n(), 2.6))
    tooth = np.tanh(tooth * 1.3)
    hills = norm(blur(n(), 9))
    grain = norm(blur(n(), 0.8))
    h = 1.0 * tooth + 0.55 * hills + 0.18 * grain
    # faint cotton fibres: short random strokes in the height map
    fib = np.zeros((H, W))
    for _ in range(350):  # short, gently curved
        x, y = rng.uniform(0, W), rng.uniform(0, H)
        ang, L, bend = rng.uniform(0, 2 * np.pi), rng.uniform(6, 22), rng.uniform(-0.08, 0.08)
        for _ in range(int(L)):
            x, y, ang = x + np.cos(ang), y + np.sin(ang), ang + bend
            fib[int(y) % H, int(x) % W] += 1
    h += 0.18 * norm(blur(fib, 0.9))
    # emboss: light from the top-left
    gy, gx = np.gradient(blur(h, 0.6))
    shade = -(gx * 0.7 + gy * 0.7)
    shade = shade / (shade.std() + 1e-9)
    # slow tonal unevenness (sizing / cotton density)
    tone = norm(blur(n(), 120)) * 0.6 + norm(blur(n(), 40)) * 0.4
    lum = 1 + 0.018 * shade + 0.006 * h + 0.006 * tone
    # very faint edge darkening (deckle feel), irregular
    yy, xx = np.mgrid[0:H, 0:W]
    d = np.minimum.reduce([xx, W - 1 - xx, yy, H - 1 - yy]).astype(float)
    wob = blur(n(), 25) * 18
    edge = np.clip(1 - (d + wob) / 60, 0, 1) ** 2
    lum -= 0.03 * edge
    rgb = BASE[None, None, :] * lum[..., None]
    rgb[..., 2] -= 3 * edge  # edges a touch warmer
    return Image.fromarray(np.clip(rgb, 0, 255).astype(np.uint8))


if __name__ == '__main__':
    here = os.path.dirname(os.path.abspath(__file__))
    ap = argparse.ArgumentParser()
    ap.add_argument('--seed', type=int, default=7)
    ap.add_argument('--out', default=os.path.join(here, 'assets', 'cotton-paper.jpg'))
    a = ap.parse_args()
    os.makedirs(os.path.dirname(a.out), exist_ok=True)
    make(a.seed).save(a.out, quality=88, optimize=True)
    print(a.out, os.path.getsize(a.out) // 1024, 'KB')
