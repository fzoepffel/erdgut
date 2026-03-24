# Admin box planner and `boxes:prepare`

Weekly box **eligibility** (active subscription, pause window, weekly/biweekly cadence) and **box creation** (editable status, modification deadline two days before week start) are implemented in **`App\Services\Admin\WeeklyBoxPlannerService`**.

- The Artisan command **`boxes:prepare`** uses this service so cron behaviour stays aligned with the admin planner (ERD-190).
- The admin UI calls the same service for matrix data and for “create empty box” / preset fill actions.

**Equal distribution (ERD-191):** Round-robin catalog fill for a week is documented in [`docs/ADMIN-EQUAL-DISTRIBUTION.md`](ADMIN-EQUAL-DISTRIBUTION.md) and linked from the planner.

See also [`docs/BILLING-MANUAL-LAUNCH.md`](BILLING-MANUAL-LAUNCH.md) for payment-related launch rules.
