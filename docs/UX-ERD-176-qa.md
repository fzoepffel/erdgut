# ERD-176 QA — Deliveries weekly management and preferences UX

**Ticket:** ERD-176 — Deliveries weekly management and preferences UX

## Manual checks

1. **Index timeline** — `/app/deliveries` shows the intro strip (defaults vs this week only), link to default delivery settings, and a numbered vertical timeline for upcoming weeks (German when `APP_LOCALE=de`).
2. **Default vs override** — Copy on each card states that the form applies only to that week; with an active override, the amber “one-time override” line appears.
3. **Override form** — Address, timeslot, instructions, and “skip this week only” save and reset behave as before; focus styles are emerald.
4. **Skip** — “Skip this week” POSTs and returns to the index with `delivery-week-skipped` (also from the dashboard delivery row).
5. **Postpone** — “Postpone to next week” opens a modal with plain-language copy; confirm POSTs and shows `delivery-postponed` on the index.
6. **Gift** — Gift page explains billing/skip outcome; submit redirects to index with `delivery-gifted` (or error flash when already delivered).
7. **Show URL** — Visiting `/app/deliveries/{id}` redirects to the index with `focus` on the correct base delivery (no standalone detail page).
8. **Preferences** — `/app/deliveries/preferences` saves with emerald flash and translated labels.
9. **Mobile** — Timeline cards and action buttons wrap; modals are usable on a narrow viewport.

## Automated coverage

- `tests/Feature/Delivery/DeliveryControllerTest.php`
- `tests/Feature/Delivery/DeliveryPreferencesTest.php`

## Behaviour notes

- **Skip:** `POST app/deliveries/{delivery}/skip` applies the same skipped override as the weekly form with “skip this week only”, then redirects to `app.deliveries.index` with `focus` on the base delivery and `delivery-week-skipped`.
- **Postpone:** Skips the current week and ensures the following calendar week exists as a scheduled delivery; redirect focuses the next week’s delivery.
- **`app.deliveries.show`:** Redirect-only; deep links still work for dashboard “Manage” actions.
