#!/usr/bin/env python3
"""Map a white-on-X image to white-on-transparent.

Default ``soft`` mode treats the image's brightness as alpha so existing
anti-aliasing is preserved (no jagged binary edges). Use ``--binary`` for
the previous strict 0/255 behaviour.
"""

from __future__ import annotations

import argparse
import os
import sys

import cv2
import numpy as np


def _white_min_channel(bgr: np.ndarray) -> np.ndarray:
    """Return per-pixel min(B,G,R) — the conservative 'how white' value."""
    b, g, r = cv2.split(bgr)
    return np.minimum(np.minimum(b, g), r)


def bgr_to_rgba_white_key(
    bgr: np.ndarray,
    *,
    min_white: int = 251,
    despeckle: bool = True,
) -> np.ndarray:
    """Binary key: opaque white where min(B,G,R) >= ``min_white``, else transparent."""
    m = _white_min_channel(bgr)
    alpha = np.where(m >= int(min_white), 255, 0).astype(np.uint8)
    if despeckle:
        k = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
        alpha = cv2.morphologyEx(alpha, cv2.MORPH_OPEN, k, iterations=1)
        alpha = cv2.morphologyEx(alpha, cv2.MORPH_CLOSE, k, iterations=1)

    out = np.zeros((bgr.shape[0], bgr.shape[1], 4), dtype=np.uint8)
    out[:, :, 3] = alpha
    on = alpha > 0
    out[on, :3] = 255
    return out


def bgr_to_rgba_soft(
    bgr: np.ndarray,
    *,
    lo: int = 90,
    hi: int = 245,
    gamma: float = 1.0,
    pre_blur: float = 0.0,
) -> np.ndarray:
    """Smooth key: alpha is the brightness of min(B,G,R) remapped between ``lo`` and ``hi``.

    - Pixels darker than ``lo`` become fully transparent.
    - Pixels brighter than ``hi`` become fully opaque white.
    - Anything in between is anti-aliased.
    """
    m = _white_min_channel(bgr).astype(np.float32)

    if pre_blur and pre_blur > 0:
        sigma = float(pre_blur)
        k = max(3, int(round(sigma * 6)) | 1)
        m = cv2.GaussianBlur(m, (k, k), sigma)

    span = max(1.0, float(hi - lo))
    a = np.clip((m - float(lo)) / span, 0.0, 1.0)
    if gamma and gamma > 0 and gamma != 1.0:
        a = np.power(a, 1.0 / float(gamma))

    alpha = (a * 255.0 + 0.5).astype(np.uint8)
    out = np.zeros((bgr.shape[0], bgr.shape[1], 4), dtype=np.uint8)
    out[:, :, :3] = 255
    out[:, :, 3] = alpha
    return out


def main() -> int:
    ap = argparse.ArgumentParser(
        description="Convert a white-on-X image to white-on-transparent. "
        "Soft alpha by default to keep edges smooth."
    )
    ap.add_argument("input")
    ap.add_argument("output")
    ap.add_argument(
        "--binary",
        action="store_true",
        help="Use strict 0/255 keying (same as before). Edges will be hard.",
    )
    ap.add_argument(
        "--min-white",
        type=int,
        default=251,
        help="[binary] Minimum B,G,R value to count as white (0–255).",
    )
    ap.add_argument(
        "--no-despeckle",
        action="store_true",
        help="[binary] Skip tiny open/close on the alpha mask.",
    )
    ap.add_argument(
        "--lo",
        type=int,
        default=90,
        help="[soft] Brightness fully transparent below this (0–255). Default 90.",
    )
    ap.add_argument(
        "--hi",
        type=int,
        default=245,
        help="[soft] Brightness fully opaque white at/above this (0–255). Default 245.",
    )
    ap.add_argument(
        "--gamma",
        type=float,
        default=1.0,
        help="[soft] >1 lifts mid-tones (more visible), <1 crushes (more transparent).",
    )
    ap.add_argument(
        "--pre-blur",
        type=float,
        default=0.0,
        help="[soft] Optional Gaussian sigma applied to the brightness map before remap (smooths jaggies).",
    )
    args = ap.parse_args()

    img = cv2.imread(args.input, cv2.IMREAD_COLOR)
    if img is None:
        print("cannot read", args.input, file=sys.stderr)
        return 1

    if args.binary:
        rgba = bgr_to_rgba_white_key(
            img, min_white=args.min_white, despeckle=not args.no_despeckle
        )
    else:
        rgba = bgr_to_rgba_soft(
            img,
            lo=args.lo,
            hi=args.hi,
            gamma=args.gamma,
            pre_blur=args.pre_blur,
        )
    os.makedirs(os.path.dirname(args.output) or ".", exist_ok=True)
    cv2.imwrite(args.output, rgba)
    print(args.output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
