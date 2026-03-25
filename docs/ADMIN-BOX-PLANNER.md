# Admin box planner and `boxes:prepare`

Weekly box **eligibility** (active subscription, pause window, weekly/biweekly cadence) and **box creation** (editable status, modification deadline two days before week start) are implemented in **`App\Services\Admin\WeeklyBoxPlannerService`**.

- The Artisan command **`boxes:prepare`** uses this service so cron behaviour stays aligned with the admin planner (ERD-190).
- The admin UI uses the same service for the matrix and for “create empty box” / single-box preset actions.

## Fill strategies (column actions)

| Strategy | Route / UI | Behaviour |
|----------|------------|-----------|
| **Random preset** | `GET/POST …/bulk-random-preview` → `…/bulk-random-apply` | Per subscription: same **category counts** as `config/box.composition_by_size`, **random** catalog products per customer. Preview is read-only; apply creates the week’s box if missing (same as before). Implemented in **`BoxCompositionService`** + **`AdminBoxPlannerFillService`**. |
| **Weekly menu per size** | `GET/POST …/canonical-preview` → `…/canonical-apply` | One **shared** line list per `box_size` for the week. Cap = **minimum** `Subscription::includedValueForBudget()` among eligible subs of that size. Lines are built **deterministically**: per category, `orderBy('unit_price')->orderBy('id')`, greedy fill up to composition slot counts without exceeding the cap (**`CanonicalBoxTemplateBuilder`**). |
| **Equal distribution** | `boxes/distribute` | **Round-robin** across households for the week (ERD-191). See [`docs/ADMIN-EQUAL-DISTRIBUTION.md`](ADMIN-EQUAL-DISTRIBUTION.md). |

## Budget display (admin)

- **Catalog sum** = `box.estimated_value` (sum of `quantity × product.unit_price`, customer retail).
- **Overfilled** (red in UI) when `estimated_value > subscription->includedValueForBudget()` (TTC incl. eggs/dairy add-on). **Does not block** edits or shipping.
- Optional **planning hint**: `included × (1 − box.admin_planning_margin_rate)` from `config/box.php` / `BOX_ADMIN_PLANNING_MARGIN_RATE` — display only.

See also [`docs/BILLING-MANUAL-LAUNCH.md`](BILLING-MANUAL-LAUNCH.md) for payment-related launch rules.
