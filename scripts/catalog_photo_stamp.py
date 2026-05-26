#!/usr/bin/env python3
"""Photo → high-contrast white stamp on transparent canvas (catalog / chapter style).

Uses local deviation from a large Gaussian field plus edge fusion (Laplacian /
Scharr / local contrast). Output is RGBA with opaque white (alpha 0 or 255 only).

By default the source is Lanczos-upscaled so the shorter side is at least 2000px
before thresholding (``--work-min-short``). The layout matches a **logical**
724×1024 chapter tile, but the PNG is written at ``--output-scale`` times that
(``3`` = 2172×3072) so edges are not a handful of device pixels when you display
it at 724px wide in CSS. Packing uses INTER_AREA (shrink) or cubic + re-threshold
(grow) instead of INTER_NEAREST.

Example:
  python3 scripts/catalog_photo_stamp.py in.png out.png --edge-scale 0.34
  python3 scripts/catalog_photo_stamp.py wheat.png out.png --preset grain --close-iters 1
"""

from __future__ import annotations

import argparse
import os
import sys

import cv2
import numpy as np


def _odd(n: int) -> int:
    n = int(n)
    return n if n % 2 == 1 else n + 1


def upscale_gray_min_short_edge(gray_u8: np.ndarray, min_short: int) -> tuple[np.ndarray, float]:
    """Return (possibly upscaled grayscale, linear scale factor vs input)."""
    h, w = gray_u8.shape[:2]
    short = min(h, w)
    if short >= min_short or min_short <= 0:
        return gray_u8, 1.0
    scale = float(min_short) / float(short)
    nw = max(1, int(round(w * scale)))
    nh = max(1, int(round(h * scale)))
    up = cv2.resize(gray_u8, (nw, nh), interpolation=cv2.INTER_LANCZOS4)
    return up, scale


def build_stamp(
    gray_u8: np.ndarray,
    *,
    blur_big: int = 149,
    vol_th: int | None = None,
    edge_scale: float = 0.36,
    close_iters: int = 2,
    preset: str = "default",
) -> np.ndarray:
    gf = gray_u8.astype(np.float32)

    if preset == "grain":
        # Dense uniform texture (e.g. wheat pile): morphology-gradient ridges
        # stay sparse; avoid Otsu on max(grad, lap) which blows out to a solid sheet.
        # Slightly larger footprint than 5×5 so ridges survive Lanczos upscaling.
        k_grad = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (7, 7))
        grad = cv2.morphologyEx(gray_u8, cv2.MORPH_GRADIENT, k_grad)
        if vol_th is None:
            # ~10–15% ink on typical wheat pile photos at p88; raise vol_th manually if too sparse.
            thg = int(np.clip(np.percentile(grad, 86.0), 112, 172))
        else:
            thg = int(vol_th)
        _, grad_bin = cv2.threshold(grad, thg, 255, cv2.THRESH_BINARY)
        volume_bin = grad_bin
        k3 = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
        stamp = cv2.morphologyEx(
            volume_bin, cv2.MORPH_CLOSE, k3, iterations=close_iters
        )
        return stamp
    else:
        k = _odd(blur_big)
        blur_hi = cv2.GaussianBlur(gf, (k, k), 0)
        vd = np.clip(blur_hi - gf, 0, 255)
        vu = np.clip(gf - blur_hi, 0, 255)
        volume_sig = np.maximum(vd, vu).astype(np.uint8)

        if vol_th is None:
            ret_v, _ = cv2.threshold(
                volume_sig, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU
            )
            vol_th = int(np.clip(ret_v * 0.58, 7, 42))
        _, volume_bin = cv2.threshold(volume_sig, vol_th, 255, cv2.THRESH_BINARY)

    blur_lo = cv2.GaussianBlur(gf, (23, 23), 0)
    local_contrast = np.abs(gf - blur_lo).astype(np.uint8)

    lap_src = cv2.GaussianBlur(gray_u8, (3, 3), 0).astype(np.uint8)
    lap = cv2.convertScaleAbs(cv2.Laplacian(lap_src, cv2.CV_16S, ksize=3))

    g5 = cv2.GaussianBlur(gray_u8, (5, 5), 0).astype(np.uint8)
    sx = cv2.Scharr(g5, cv2.CV_32F, 1, 0)
    sy = cv2.Scharr(g5, cv2.CV_32F, 0, 1)
    sch = cv2.normalize(cv2.magnitude(sx, sy), None, 0, 255, cv2.NORM_MINMAX).astype(
        np.uint8
    )

    fuse = cv2.max(
        lap,
        cv2.addWeighted(local_contrast, 0.40, sch, 0.78, -5.5),
    ).astype(np.uint8)
    fuse = cv2.GaussianBlur(fuse, (3, 3), 0)

    ret_e, _ = cv2.threshold(fuse, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    edge_th = int(np.clip(ret_e * edge_scale, 6, 52))
    _, edge_bin = cv2.threshold(fuse, edge_th, 255, cv2.THRESH_BINARY)

    stamp = cv2.bitwise_or(volume_bin, edge_bin)
    k3 = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
    stamp = cv2.morphologyEx(stamp, cv2.MORPH_CLOSE, k3, iterations=close_iters)
    return stamp


def pack_canvas(
    stamp: np.ndarray,
    *,
    logical_w: int = 724,
    logical_h: int = 1024,
    margin_x: int = 48,
    margin_y: int = 80,
    bottom_pad: int = 100,
    output_scale: int = 3,
) -> np.ndarray:
    """Place binary ``stamp`` on an RGBA canvas ``output_scale`` × logical chapter size."""
    ys, xs = np.where(stamp > 0)
    if len(xs) == 0:
        raise ValueError("empty stamp — relax thresholds or check input")

    s = max(1, int(output_scale))
    canvas_w = logical_w * s
    canvas_h = logical_h * s
    mx = int(round(margin_x * s))
    my = int(round(margin_y * s))
    bp = int(round(bottom_pad * s))
    pad = int(round(28 * s))
    side_trim = int(round(44 * s))

    y0 = max(0, ys.min() - pad)
    y1 = min(stamp.shape[0] - 1, ys.max() + pad)
    x0 = max(0, xs.min() - pad)
    x1 = min(stamp.shape[1] - 1, xs.max() + pad)
    roi = stamp[y0 : y1 + 1, x0 : x1 + 1]

    ch, cw = roi.shape[:2]
    avail_h = canvas_h - my - bp
    nh = avail_h
    nw = max(1, int(round(cw * nh / ch)))

    def _resize_mask(src: np.ndarray, tw: int, th: int) -> np.ndarray:
        """Avoid INTER_NEAREST 'fat pixels': area when shrinking, cubic+rebinarize when growing."""
        sh, sw = src.shape[:2]
        if tw <= 0 or th <= 0:
            return src
        if tw < sw or th < sh:
            scaled_f = cv2.resize(src.astype(np.float32), (tw, th), interpolation=cv2.INTER_AREA)
            _, out = cv2.threshold(scaled_f.astype(np.uint8), 127, 255, cv2.THRESH_BINARY)
            return out
        scaled = cv2.resize(src, (tw, th), interpolation=cv2.INTER_CUBIC)
        _, out = cv2.threshold(scaled, 200, 255, cv2.THRESH_BINARY)
        return out

    scaled = _resize_mask(roi, nw, nh)

    canvas = np.zeros((canvas_h, canvas_w, 4), dtype=np.uint8)
    y_off = my + max(0, (avail_h - nh) // 2)
    x_off = mx
    if x_off + nw > canvas_w - side_trim:
        nw2 = canvas_w - side_trim - x_off
        nh2 = max(1, int(round(ch * nw2 / cw)))
        scaled = _resize_mask(roi, nw2, nh2)

    h2, w2 = scaled.shape[:2]
    canvas[y_off : y_off + h2, x_off : x_off + w2, :3] = 255
    canvas[y_off : y_off + h2, x_off : x_off + w2, 3] = scaled
    return canvas


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("input")
    ap.add_argument("output")
    ap.add_argument("--blur-big", type=int, default=149)
    ap.add_argument("--vol-th", type=int, default=None, help="Fixed volume threshold")
    ap.add_argument("--edge-scale", type=float, default=0.36)
    ap.add_argument("--close-iters", type=int, default=2)
    ap.add_argument(
        "--preset",
        choices=("default", "grain"),
        default="default",
        help="grain = morphology-gradient volume for dense textures (wheat, rice, …)",
    )
    ap.add_argument(
        "--work-min-short",
        type=int,
        default=2000,
        help="Upscale grayscale so min(h,w) is at least this before stamping (0=off). Reduces blocky upscaling.",
    )
    ap.add_argument(
        "--output-scale",
        type=int,
        default=3,
        help="PNG size = output-scale × logical 724×1024 (default 3 → 2172×3072). Use 2 for smaller files.",
    )
    args = ap.parse_args()

    img = cv2.imread(args.input)
    if img is None:
        print("cannot read", args.input, file=sys.stderr)
        return 1

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    gray, scale = upscale_gray_min_short_edge(gray, args.work_min_short)
    blur_big = _odd(int(np.clip(round(args.blur_big * scale), 31, 301)))

    stamp = build_stamp(
        gray,
        blur_big=blur_big,
        vol_th=args.vol_th,
        edge_scale=args.edge_scale,
        close_iters=args.close_iters,
        preset=args.preset,
    )
    canvas = pack_canvas(stamp, output_scale=args.output_scale)
    os.makedirs(os.path.dirname(args.output) or ".", exist_ok=True)
    cv2.imwrite(args.output, canvas)
    print(args.output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
