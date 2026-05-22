# Dashboard rebuild — editorial-led customer dashboard

Plan for redesigning the logged-in customer dashboard (`/app/dashboard`) to match the editorial Lovable prototype (`fresh-box-delight.lovable.app`) while staying in the existing visual system (Fraunces + Inter, cream/ink/moss palette, chapter accents).

The visual identity is already largely aligned with the prototype. What changes is the **information architecture** and the addition of two new editorial surfaces (recipe of the week + box-contents illustration / photo) plus a richer per-item presentation pulled forward onto the dashboard.

## Goal

Reorder the logged-in dashboard so a returning subscriber lands on:

1. A big personal greeting + this week's box meta + the existing weekly actions
2. The visual portfolio that could land in the box this week (or actual items once composed)
3. Producer of the week (landscape feature)
4. Recipe of the week (editorial-only, no admin UI in v1)
5. The next four weeks (almanac row table)
6. An editorial footer band

The redesign covers the **steady-state path** (active subscription, with or without a composed box, including paused state). The existing `NewVisitor`, `TrialOnTheWay`, `TrialDelivered`, and `ResumeSubscriptionSetup` blocks render above the new sections unchanged — they can be folded into the new language in a later phase.

## Editorial-only constraint (v1)

- **No new admin UI** for producer-of-the-week or recipe-of-the-week in v1.
- Producer rotates via the existing `EditorialContent::producerOfTheWeek()` (ISO-week index → `PRODUCER_SLUGS`).
- Recipe rotates via a new `EditorialContent::recipeOfTheWeek()` returning translation-keyed static content (same pattern as `weeklyIntro` / `founderNote`).
- Admin tooling lands in a follow-up phase (see ERD-357 / ERD-358).

## Constraints carried from existing code

- Delivery day is **Wednesday** — `config/delivery.php` `allowed_weekdays = [3]`. Hero copy reads this dynamically.
- "Confirm delivery" is **not** in scope — deliveries are auto-confirmed unless the subscription is paused. No new "confirm" affordance.
- Surfaced weekly actions are the existing ones only: `Skip next week`, `Plan a longer break` (= existing pause modal), `End the break` (= existing resume modal), `Manage subscription`, `View current box`.
- "Swap an item" from the prototype is **not** added — we don't currently support item swaps.

## Section-by-section spec

### § 1 — Hero band

Two-column layout. Left: greeting + lead + action row. Right: box-meta-framed illustration (later: real photo).

**Left column**

- Eyebrow: `KAPITEL {iso_week} · DEINE WOCHENBOX`
- Headline (Fraunces, ~text-6xl, first name italicised):
  - Box composed and date known: _Guten Morgen, {Name}. Deine Box kommt am {Wochentag}._
  - Active but no date yet: _Guten Morgen, {Name}. Deine nächste Box wird geplant._
  - Pause active: _Guten Morgen, {Name}. Pause bis zum {Datum}._
- Lead paragraph: `{N} Produkte diese Woche, kuratiert von {M} Höfen rund um Berlin.` — derived from current box items if composed; otherwise: `{M} Höfe stehen diese Woche im Sortiment.`
- Action row (drop "Confirm delivery"):
  - **Solid CTA**: `Aktuelle Box ansehen` → `app.boxes.current` (only when a box is composed)
  - **Link**: `Nächste Woche überspringen` → POSTs `app.subscriptions.skip.next`
  - **Link**: `Längere Pause planen` → opens `confirm-subscription-pause` modal
  - **Link**: `Pause beenden` → opens `confirm-subscription-resume` (only if paused)
  - **Link**: `Abo verwalten` → `app.subscriptions.show`

**Right column** (box-meta frame around illustration/photo)

- Top-left eyebrow caps: `BOX № {str_pad($box->id, 4, '0', STR_PAD_LEFT)}`
- Top-right eyebrow caps: `{box_size} · {people_label}` (Small ≈ 1 Person, Medium ≈ 2 Personen, Large ≈ 4 Personen)
- Centerpiece:
  - If `$box->photo_path` set (future) → real photo
  - Else → illustration from `public/images/box-growth/box-contents-illustration.png` (placeholder, single shared illustration for v1)
- Bottom-left eyebrow caps: `FRISCH AUS DER WOCHEN-ERNTE`
- Bottom-right eyebrow caps:
  - With illustration: `FOTO FOLGT AM {Wochentag-1}`
  - With photo: hide

### § 2 — Manifest: "What's inside" / "What could land in your box"

**Header**

- Eyebrow: `WAS IN DER BOX IST` (composed) or `WAS DIESE WOCHE IM SORTIMENT IST` (not composed)
- Title (Fraunces, text-4xl): `Das Manifest, Stück für Stück.`
- Subtitle: `Jeder Eintrag signiert von den Händen, die ihn angebaut haben.`

**Grid** — 3 columns desktop, 2 tablet, 1 mobile. Each card (= new `<x-customer.item-card>`):

- 4:3 photo with `№ {nn}` chip in top-left corner (small inset eyebrow box)
- Below photo: name (Fraunces, text-xl) at left, quantity (eyebrow caps, text-xs) at right
- Hairline divider
- Producer in eyebrow caps: `{PRODUCER NAME} · {CITY}`
- 1-line short description (italic Fraunces)

**Data source**

- Box composed → iterate `$currentBox->items` with explicit numbering.
- Box not composed → iterate seasonal portfolio. v1: 6 active products selected stably per ISO week (no curation flag yet). Quantity hidden in this state.

The existing per-item rich rendering at `boxes/show.blade.php:177-208` is refactored into `<x-customer.item-card>` and reused on both `boxes/show` and the dashboard.

**Open product-model question**: `products.short_description` field may not exist. If missing, add a nullable column in the manifest ticket and fall back to product `description` truncated to 1 line until admins fill it in.

### § 3 — Producer of the week (landscape feature)

Big left portrait (≈ 60% width on desktop), text right.

- Eyebrow: `BAUER DER WOCHE`
- Quote (Fraunces, large italic, chapter-produce green): `"{quote}"`
- Hairline + small attribution: `{name} · {farm}`
- Bio paragraph (3–4 lines)
- Link: `Sein/Ihr Kapitel lesen →` → public producer detail page

**Producer pick rule** (matches existing `boxes/show.blade.php:37-44`): if any item in this week's box comes from a producer in `PRODUCER_SLUGS`, prefer that producer over the ISO-week rotation — anchors the quote to a real item this week.

**Component**: new `<x-customer.producer-feature>`. The existing small `<x-customer.producer-spotlight>` stays and continues to be used by other surfaces.

### § 4 — Recipe of the week

Two-column. Text left, dish photo right.

- Eyebrow: `EIN VORSCHLAG AUS DER KÜCHE`
- Title (Fraunces, text-4xl): recipe title
- Description paragraph
- Three labelled metrics (eyebrow caps + Fraunces values): `ZEIT / 20 min`, `PORTIONEN / 2`, `AUS DER BOX / 4 von 6`
- Link: `Rezept öffnen →`

**Data source (editorial-only)**:

- `EditorialContent::recipeOfTheWeek(?CarbonInterface $when = null): array` — returns `{slug, title, description, time_minutes, serves, ingredient_slugs[], photo}` keyed by ISO week, same pattern as `producerOfTheWeek()`.
- "Aus der Box X von Y" computes overlap between `ingredient_slugs` and `$currentBox->items->pluck('product.slug')`. Hidden when no box is composed.
- `Rezept öffnen →` links to a minimal `resources/views/recipes/show.blade.php` that renders editorial-content payload. No `Recipe` Eloquent model in v1.

**Section is hidden** if no recipe content is configured for the current ISO week.

### § 5 — Almanac (your next four weeks)

Full-width row table.

- Header row: `DER ALMANACH` (eyebrow) + `Deine nächsten vier Wochen.` (Fraunces title) + right-aligned `Vollen Kalender öffnen →` link to deliveries index
- 4 rows × columns: `WK {iso}` | `Mi, {day} {month}` | label | status badge
  - Label rules:
    - Composed box → `Box №{id}`
    - Not yet composed → editorial seasonal hint from `EditorialContent::weekTagline($weekStart)` (e.g. `Spargel-Höhepunkt`)
    - Within pause window → `In deiner Pause`
    - First week back from pause → `Zurück ab KW {n}`
  - Status badge:
    - This week's composed box: `UNTERWEGS` (chapter-meat red)
    - Future scheduled: `EINGEPLANT` (ink)
    - Skipped via skip-set or pause: `ÜBERSPRUNGEN` (struck-through, ink/50)

Data already available in `$upcomingBoxes` + `$primarySubscription->isWithinPauseWindow()` + `subscription_skip_weeks`.

Replaces the vertical-card list at `dashboard.blade.php:499-537`.

### § 6 — Editorial footer band

Full-bleed band, chapter-produce green background.

- Small leaf/onion mark icon on the left
- Fraunces statement (cream): `Eine Box pro Woche, im Rhythmus der Jahreszeiten.`
- Right-aligned nav columns: Hilfe · Bauern · Konto · Pausieren · Freund:in werben · Abmelden

`<x-app-footer>` may already cover this; if so, restyle and slot in. Otherwise build new.

## State coverage matrix

| State                            | § 1 Hero              | § 2 Manifest           | § 3 Producer | § 4 Recipe                 | § 5 Almanac |
| -------------------------------- | --------------------- | ---------------------- | ------------ | -------------------------- | ----------- |
| Active + box composed            | full                  | actual items           | ✓            | ✓ (overlap line shown)     | ✓           |
| Active + box not yet composed    | "wird geplant" copy   | portfolio (6 items)    | ✓            | ✓ (no overlap line)        | ✓           |
| Paused (inside window)           | pause-aware headline  | hidden                 | ✓            | hidden                     | ✓ (PAUSED)  |
| First week, no box yet           | "erste Box kommt am…" | portfolio (6 items)    | ✓            | ✓                          | ✓           |
| New visitor / Trial / Resume-setup | **unchanged today** — existing blocks render above § 1                                          |

## Component inventory

| New                                    | Purpose                                                                |
| -------------------------------------- | ---------------------------------------------------------------------- |
| `<x-customer.dashboard-hero>`          | § 1 — two-column greeting + meta-framed illustration                   |
| `<x-customer.item-card>`               | § 2 — reusable item tile (photo + №-tag + name + qty + producer + 1-liner) |
| `<x-customer.manifest-grid>`           | § 2 — section header + 3-up grid wrapper                                |
| `<x-customer.producer-feature>`        | § 3 — landscape producer hero                                          |
| `<x-customer.recipe-feature>`          | § 4 — two-column recipe hero                                           |
| `<x-customer.almanac-rows>`            | § 5 — wide row table for upcoming boxes                                |
| `<x-customer.editorial-footer-band>`   | § 6 — full-bleed green editorial band                                  |
| `resources/views/recipes/show.blade.php` (+ controller + route) | Minimal recipe detail page (editorial payload)         |
| `EditorialContent::recipeOfTheWeek()`  | Rotating recipe payload                                                |
| `EditorialContent::weekTagline()`      | Seasonal one-liner per upcoming week                                   |

| Reused                                  |
| --------------------------------------- |
| `<x-customer.producer-spotlight>`       |
| `<x-customer.story-block>`              |
| `EditorialContent::producerOfTheWeek()` |

## Assets

- **Box-contents illustration** (v1 placeholder): `public/images/box-growth/box-contents-illustration.png` — single linocut-style illustration shared across box sizes. Real per-box photos handled by a future `boxes.photo_path` column (out of scope for v1; see ERD-356 follow-up).

## Phasing → ticket map

Each phase is one shippable PR / one branch. The dashboard renders coherently (if incomplete) after each step.

| Ticket   | Phase                                  | Branch suggestion                                     |
| -------- | -------------------------------------- | ----------------------------------------------------- |
| ERD-351  | § 1 — Dashboard hero                   | `feature/erd-351-dashboard-hero`                      |
| ERD-352  | § 2 — Manifest grid + item card        | `feature/erd-352-dashboard-manifest`                  |
| ERD-353  | § 3 — Producer feature                 | `feature/erd-353-dashboard-producer-feature`          |
| ERD-354  | § 4 — Recipe feature (editorial only)  | `feature/erd-354-dashboard-recipe-feature`            |
| ERD-355  | § 5 — Almanac rows                     | `feature/erd-355-dashboard-almanac`                   |
| ERD-356  | § 6 — Editorial footer band            | `feature/erd-356-dashboard-footer-band`               |
| ERD-357  | **Follow-up**: admin UI for producer of the week | `feature/erd-357-admin-producer-of-week`     |
| ERD-358  | **Follow-up**: admin UI for recipe of the week   | `feature/erd-358-admin-recipe-of-week`       |

## Out of scope (v1)

- `boxes.photo_path` + admin upload UI (illustration stays as the right-column visual until this lands)
- "Swap an item" action — we don't support item-level swaps
- Real `Recipe` Eloquent model — handled in ERD-358
- Per-product `short_description` curation tooling (column may be added in ERD-352 if absent; admin curation UI is a separate follow-up if needed)
- Trial / new-visitor / resume-setup re-skin to the new editorial language
