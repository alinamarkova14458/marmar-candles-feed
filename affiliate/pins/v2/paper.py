"""Procedural cold-press cotton watercolor paper -> assets/cotton-paper.jpg (1000x2000, no downloads).

Looks like a scan of real cotton paper under soft side light: a pebbly height map (multi-octave
noise, rounded bumps ~6-14 px plus finer grain), Lambert-shaded with light from the upper left,
low contrast, cool near-white (#F4F2EE), even lighting (no vignette).
  python3 paper.py [--seed 7] [--strength 1.0] [--out assets/cotton-paper.jpg]
"""
import argparse, os
import numpy as np
from PIL import Image

W, H = 1000, 2000
BASE = np.array([245, 243, 239], float)  # #F4F2EE


def blur(a, sigma, sy=None):
    """Gaussian blur via FFT (periodic), no scipy needed; sy != sigma -> slightly elongated grain."""
    sy = sigma if sy is None else sy
    fy = np.fft.fftfreq(a.shape[0])[:, None]
    fx = np.fft.fftfreq(a.shape[1])[None, :]
    return np.real(np.fft.ifft2(np.fft.fft2(a) * np.exp(-2 * np.pi ** 2 * ((sigma * fx) ** 2 + (sy * fy) ** 2))))


def norm(a):
    return (a - a.mean()) / (a.std() + 1e-9)


def make(seed, strength=1.0):
    rng = np.random.default_rng(seed)
    n = lambda: rng.standard_normal((H, W))
    # pebbles: rounded bumps; tanh flattens the valleys/tops so they read as pressed felt texture
    peb = np.tanh(norm(blur(n(), 3.0, 3.8)) * 0.9)  # felt-pressed pebbles, a touch taller than wide
    mid = norm(blur(n(), 1.6, 2.2))
    hills = norm(blur(n(), 12))
    grain = norm(blur(n(), 0.8))
    h = 1.0 * peb + 0.35 * mid + 0.25 * hills + 0.06 * grain
    # Lambert shading: normal from the height gradient, soft light from the upper left
    gy, gx = np.gradient(h * 1.6)
    nz = 1 / np.sqrt(gx ** 2 + gy ** 2 + 1)
    nx, ny = -gx * nz, -gy * nz
    L = np.array([-0.55, -0.55, 0.63]); L /= np.linalg.norm(L)
    lam = nx * L[0] + ny * L[1] + nz * L[2]
    lam = (lam - lam.mean()) / (lam.std() + 1e-9)
    lum = 1 + strength * (0.021 * lam + 0.003 * h)
    rgb = BASE[None, None, :] * lum[..., None]
    return Image.fromarray(np.clip(rgb, 0, 255).astype(np.uint8))


if __name__ == '__main__':
    here = os.path.dirname(os.path.abspath(__file__))
    ap = argparse.ArgumentParser()
    ap.add_argument('--seed', type=int, default=7)
    ap.add_argument('--strength', type=float, default=1.0)
    ap.add_argument('--out', default=os.path.join(here, 'assets', 'cotton-paper.jpg'))
    a = ap.parse_args()
    os.makedirs(os.path.dirname(a.out), exist_ok=True)
    make(a.seed, a.strength).save(a.out, quality=88, optimize=True)
    print(a.out, os.path.getsize(a.out) // 1024, 'KB')
