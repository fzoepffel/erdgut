# Mock asset credits

All assets in `public/images/mock/` and `public/videos/mock/` are placeholders sourced from free-to-use libraries (Unsplash and Mixkit). They are intended to be swapped for real Erdgut photography before production launch.

## Licensing summary

- **Unsplash** — Photos from [unsplash.com](https://unsplash.com) are provided under the [Unsplash License](https://unsplash.com/license): free to use for commercial and non-commercial purposes, attribution appreciated but not required.
- **Mixkit** — Videos from [mixkit.co](https://mixkit.co) are provided under the [Mixkit License](https://mixkit.co/license/): free to use including commercially, with some restrictions (no redistribution of the raw asset).

## Photos (Unsplash)

| File | Unsplash photo ID |
| ---- | ----------------- |
| `hero/hero-farm.jpg` | `1500382017468-9049fed747ef` |
| `hero/hero-field.jpg` | `1464226184884-fa280b87c399` |
| `hero/about-hero.jpg` | `1523348837708-15d4a09cfac2` |
| `hero/hero-poster.jpg` | extracted from `videos/mock/newvid.mp4` (frame at 1s) |
| `producers/elena.jpg` | `GV3u5aUUHwU` |
| `producers/marcus.jpg` | `RzSwCxlXbxs` |
| `producers/sophia.jpg` | `K8d7oyd8spQ` |
| `producers/thomas.jpg` | `xp3xtQW3pqs` |
| `producers/anna.jpg` | `Al8e7zVpdkA` |
| `producers/jakob.jpg` | `7JPGg8S-QD4` |
| `stalls/fish/scene.jpg` | `0Ar1dmjSrKA` |
| `stalls/fish/product.jpg` | `0Ar1dmjSrKA` |
| `stalls/meat/scene.jpg` | `kqytZ3VFb_o` |
| `stalls/meat/product.jpg` | `kqytZ3VFb_o` |
| `stalls/produce/scene.jpg` | `Msb2ZTv_k_0` |
| `stalls/produce/product.jpg` | `Zaiuy5dKeCk` |
| `stalls/dairy/scene.jpg` | `YW_5rJvAdKw` |
| `stalls/dairy/product.jpg` | `K8d7oyd8spQ` |
| `stalls/bakery/scene.jpg` | `rsWZ-P9FbQ4` |
| `stalls/bakery/product.jpg` | `NqyJZprqP9c` |
| `products/vegetables.jpg` | `Msb2ZTv_k_0` |
| `products/fruit.jpg` | `d2M1utLpT9E` |
| `products/dairy.jpg` | `YW_5rJvAdKw` |
| `products/bakery.jpg` | `rsWZ-P9FbQ4` |
| `products/fish.jpg` | `0Ar1dmjSrKA` |
| `products/meat.jpg` | `kqytZ3VFb_o` |
| `boxes/box-hero.jpg` | `1610348725531-843dff563e2c` |

Full photo URL pattern: `https://unsplash.com/photos/<photo ID>`.

## Videos

| File | Source |
| ---- | ------ |
| `../videos/mock/newvid.mp4` | **Homepage hero** — project-provided clip (ensure you have rights to use it on the site). |
| `../videos/mock/hero-loop.mp4` | Legacy placeholder: [Mixkit clip 4144](https://mixkit.co/free-stock-video/las-vegas-panning-time-lapse-4144/) — not used on the homepage when `newvid.mp4` is wired in Blade. |
| `../videos/mock/hero-loop.webm` | Legacy VP9/WebM variant of the Mixkit clip — not used when only MP4 is referenced. |

## Replacement guidance

These files are all under `public/images/mock/` and `public/videos/mock/`. Swap them in-place with real photography (same file names, same dimensions) and the rest of the templates will keep working without code changes.
