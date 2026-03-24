# Shop catalog and product detail UX (ERD-178)

**Ticket:** ERD-178 — Shop catalog and product detail UX  
**Routes:** `app.shop.index`, `app.shop.show`  
**Purpose:** Manual QA after catalog toolbar (search, sort), mobile filter discoverability, i18n, PDP guidance when no editable box, and optional product metadata for dietary-style info.

---

## Preconditions

- Authenticated subscriber account.
- Shop extras feature enabled (`features.shop_extras_enabled` true).
- Mix of products: multiple categories, producers, seasonal flag, varied prices.
- At least one product with `metadata.characteristics` as an array of strings (e.g. `["Organic", "Regional"]`) and/or `metadata.dietary_notes` as a short string — optional; used only when present.

---

## Catalog (`app/shop/index`)

1. **Intro strip** — Emerald info panel explains extras; links to current week box and subscription overview work.
2. **Search** — Enter a substring of a product name, apply; grid only shows matching names. Special characters in names (e.g. `%`, `_`) still match as literal text where expected.
3. **Sort** — `Name A–Z`, `Price: low to high`, `Price: high to low` change order; pagination keeps `sort` in query string.
4. **Filter composition** — With category + producer + `q` + `sort` active, chip links for other categories/producers preserve `q` and `sort` (and other active filters) in the URL.
5. **Clear** — “Clear all” / “Clear all filters” resets to the default catalog (no stray query params that still filter).
6. **Mobile filters** — Below `lg`, “Category & producer filters” toggles chip blocks; toggle is keyboard-focusable and `aria-expanded` reflects state; at `lg` and up, chips are always visible without the toggle.
7. **Empty state** — With filters/search that yield zero products, copy is understandable; clear link appears when filters are active.
8. **German** — With `APP_LOCALE=de`, spot-check new strings (toolbar, intro, empty state, chips) read naturally.

---

## Product detail (`app/shop/show`)

1. **No editable box** — When there is no current editable box (no box in window, or not `Editable`, or deadline passed), a neutral panel explains the requirement; primary CTA goes to current week box; secondary to dashboard. No “Add to box” button in that state.
2. **Editable box** — With an editable box, “Add to box” works as before (JSON API unchanged); button shows loading/disabled while submitting.
3. **Errors** — Failed add-to-box shows an error region with `role="alert"` and polite live region (screen reader spot-check optional).
4. **Metadata** — If `metadata.characteristics` is a non-empty array of strings, a “Characteristics” list appears. If `metadata.dietary_notes` is a non-empty string, a “Dietary notes” section appears.
5. **Category / seasonal** — Labels use the same translation pattern as the catalog card (no raw `ucfirst` only).
6. **Images** — Hero image uses lazy loading where supported.

---

## Regression

- Unpublished or unavailable products: still 404 on show; index still excludes unpublished.
- Guest: still redirected to login for shop routes.

---

## Automated coverage

Feature tests in `tests/Feature/Shop/ShopControllerTest.php` cover search, sort validation, price ordering, chip query preservation, metadata on PDP, and no-box guidance copy/CTAs.
