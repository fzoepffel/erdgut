# ERD-175 QA — Subscription management UX

**Ticket:** ERD-175 — Subscription management UX end to end

## Manual checks

1. **No subscription** — `/app/subscriptions` shows empty state with primary CTA to create; copy is clear (German when `APP_LOCALE=de`).
2. **Create (step 1)** — Plan (weekly/biweekly), box size, preferences, allergies; step banner references payment as step 2; validation errors show per field (e.g. missing/invalid `plan`).
3. **After create** — Redirect to overview with success + step-2 hint; “Set up payment method” uses emerald styling and links to `app.payments.setup`.
4. **Overview** — Sections: subscription & box, diet & allergies (human yes/no, no raw JSON), delivery pause, billing & payments; status badge and CTAs match subscription state (cancelled hides pause/cancel appropriately).
5. **Edit** — Plan can be changed; helper text explains next charge recalculation for active subscriptions; emerald form controls.
6. **Pause modal** — Explains billing/delivery impact; date validation errors reopen modal with field errors.
7. **Resume** — Confirmation modal; POST still works from modal submit.
8. **Cancel modal** — Consequence copy + optional reason; destructive confirmation.
9. **Mobile** — Primary actions wrap; modals usable on narrow viewports.

## Automated coverage

- `tests/Feature/Subscription/SubscriptionOverviewTest.php`
- `tests/Feature/Subscription/SubscriptionUpdateTest.php`
- `tests/Feature/Subscription/SubscriptionLifecycleActionsTest.php`

## Behaviour notes

- **Plan on subscribe:** `POST app/subscriptions` requires `plan` (`weekly` | `biweekly`).
- **Plan on update:** `PATCH app/subscriptions` requires `plan`. For **active** subscriptions, changing `plan` recalculates `next_charge_date` via `SubscriptionLifecycleService::computeNextChargeDate()` from today.
