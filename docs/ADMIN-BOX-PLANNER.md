# Admin box planner and `boxes:prepare`

Weekly box **eligibility** (active subscription, pause window, weekly/biweekly cadence) and **box creation** (editable status, modification deadline two days before week start) are implemented in **`App\Services\Admin\WeeklyBoxPlannerService`**.

- The Artisan command **`boxes:prepare`** uses this service so cron behaviour stays aligned with the admin planner (ERD-190).
- The admin UI calls the same service for matrix data and for “create empty box” / preset fill actions.

See also [`docs/BILLING-MANUAL-LAUNCH.md`](BILLING-MANUAL-LAUNCH.md) for payment-related launch rules.
