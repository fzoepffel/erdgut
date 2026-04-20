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
| `producers/elena.jpg` | `1605723517503-3cadb5818a0c` |
| `producers/marcus.jpg` | `1507003211169-0a1dd7228f2d` |
| `producers/sophia.jpg` | `1544005313-94ddf0286df2` |
| `producers/thomas.jpg` | `1506794778202-cad84cf45f1d` |
| `producers/anna.jpg` | `1619946794135-5bc917a27793` |
| `producers/jakob.jpg` | `1438761681033-6461ffad8d80` |
| `stalls/fish/scene.jpg` | `1534043464124-3be32fe000c9` |
| `stalls/fish/product.jpg` | `1559339352-11d035aa65de` |
| `stalls/meat/scene.jpg` | `1607623814075-e51df1bdc82f` |
| `stalls/meat/product.jpg` | `1607623814075-e51df1bdc82f` |
| `stalls/produce/scene.jpg` | `1542838132-92c53300491e` |
| `stalls/produce/product.jpg` | `1498579150354-977475b7ea0b` |
| `stalls/dairy/scene.jpg` | `1488477181946-6428a0291777` |
| `stalls/dairy/product.jpg` | `1628088062854-d1870b4553da` |
| `stalls/bakery/scene.jpg` | `1509440159596-0249088772ff` |
| `stalls/bakery/product.jpg` | `1509440159596-0249088772ff` |
| `products/vegetables.jpg` | `1610348725531-843dff563e2c` |
| `products/fruit.jpg` | `1610832958506-aa56368176cf` |
| `products/dairy.jpg` | `1563636619-e9143da7973b` |
| `products/bakery.jpg` | `1608198093002-ad4e005484ec` |
| `products/fish.jpg` | `1485704686097-ed47f7263ca4` |
| `products/meat.jpg` | `1607623814075-e51df1bdc82f` |
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
