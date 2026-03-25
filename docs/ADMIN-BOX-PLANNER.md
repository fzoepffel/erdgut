# Admin box planner and `boxes:prepare`

Weekly box **eligibility** (active subscription, pause window, weekly/biweekly cadence) and **box creation** (editable status, modification deadline two days before week start) are implemented in **`App\Services\Admin\WeeklyBoxPlannerService`**.

- The Artisan command **`boxes:prepare`** uses this service so cron behaviour stays aligned with the admin planner (ERD-190).
- The admin UI uses the same service for the matrix and for “create empty box”. Bulk fills use **weekly menu (canonical)** only; `boxes:prepare` / cron may still use random composition until aligned separately.

## Fill strategies (column actions)

| Strategy | Route / UI | Behaviour |
|----------|------------|-----------|
| **Weekly menu per size** | `GET …/canonical-preview` → `POST …/canonical-apply` | One **shared** line list per `box_size` for the week. Cap = **minimum** `Subscription::includedValueForBudget()` among eligible subs of that size. The preview **pre-fills** a greedy template (**`CanonicalBoxTemplateBuilder`**); admins edit quantities, add/remove catalog lines, then POST applies the same lines to every eligible box of that size (**`AdminBoxPlannerFillService::applyCanonicalFromRequest`**). |
| **Equal distribution** | `boxes/distribute` | **Round-robin** across households for the week (ERD-191). See [`docs/ADMIN-EQUAL-DISTRIBUTION.md`](ADMIN-EQUAL-DISTRIBUTION.md). |

## Budget display (admin)

- **Catalog sum** = `box.estimated_value` (sum of `quantity × product.unit_price`, customer retail).
- Planner rows show **nominal** TTC from `config('box.included_value_by_size.{size}')` (`Subscription::nominalIncludedValueTtc()`) and **effective** charge/budget (`includedValueForBudget()`, trial or post-trial plus add-ons). A short notice appears when trial billing is pending and the two differ.
- **Overfilled** (red in UI) when `estimated_value > subscription->includedValueForBudget()` (TTC incl. eggs/dairy add-on). **Does not block** edits or shipping.
- Optional **planning hint**: `included × (1 − box.admin_planning_margin_rate)` from `config/box.php` / `BOX_ADMIN_PLANNING_MARGIN_RATE` — display only.

See also [`docs/BILLING-MANUAL-LAUNCH.md`](BILLING-MANUAL-LAUNCH.md) for payment-related launch rules.
