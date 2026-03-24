# Admin equal distribution (ERD-191)

## Purpose

For a chosen **Monday** (`week_start_date`), replace **core** box lines (`box_items.is_extra = false`) across all **eligible, editable** boxes for that week with a **deterministic round-robin** pick from the live catalog. **Shop extras** (`is_extra = true`) are **not** removed.

## Shared rules with the planner

- **Eligibility** (active subscription, pause window, weekly/biweekly cadence) and **box provisioning** use [`App\Services\Admin\WeeklyBoxPlannerService`](../app/Services/Admin/WeeklyBoxPlannerService.php), same as the weekly planner and bulk preset: `eligibleSubscriptionsForWeek` + `ensureEmptyBox`.
- Boxes that exist but are **not** `editable` (e.g. locked or delivered) are **skipped** and counted in the apply flash message.

## Algorithm (MVP)

1. Sort target boxes by `id`.
2. Build the **union of categories** appearing in `config('box.composition_by_size.{size}')` for all target subscriptions’ `box_size` values, sorted alphabetically.
3. For each category:
   - Build **slots**: for each target box in order, append that box `K` times, where `K` is the configured count for that category and the box’s size.
   - Load the product pool: `Product::visibleInCatalog()` for that category, `orderBy('id')`. If the pool is empty, the category is skipped.
   - Assign slot `i` the product `pool[i % pool->count()]`, creating one core line per assignment (`quantity` 1, `unit` from product metadata, default `piece`).
4. **Before** inserts: delete all `is_extra = false` lines for each target box (transaction).
5. Recalculate `estimated_value` with [`BoxValueRecalculationService`](../app/Support/BoxValueRecalculationService.php) per updated box.

**Caps:** Item counts follow **`composition_by_size`** (same semantics as preset fill). **`included_value_by_size` is not enforced** in v1.

**Supply:** There is **no stock/inventory** column on products yet; the catalog is treated as **unlimited**. A future stock model can cap picks.

**Idempotency:** Same week, same catalog, same boxes → same assignment (stable ordering). Re-running apply replaces core lines again with the same distribution.

## UI

- From the **box planner**, use **Equal distribution** / **Gleichverteilung** for a column week to open the preview, then confirm on the preview page.
