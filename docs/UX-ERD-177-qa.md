# ERD-177 QA — Weekly box composition UX

**Ticket:** ERD-177 — Weekly box composition UX

## Manual checks

1. **View mode** — Open `/app/boxes/current` or a specific box; header shows “This week’s box”, week date `d.m.Y`, status badge, deadline copy, read-only vs editable messaging (German when `APP_LOCALE=de`).
2. **Budget panel** — With an active subscription, see included value, estimated value, and remaining budget (same logic as shop summary). Without active subscription, included/remainder rows are omitted.
3. **Countdown** — When the box is editable and a deadline exists, “Time left to edit” updates about every minute.
4. **Edit mode** — `?mode=edit` shows larger +/- controls, remove, live estimated total, budget remainder updates with estimated value, and a mobile sticky bar with estimated value.
5. **Blocked edit** — Locked/delivered box or past deadline: amber read-only state, edit CTA disabled, blocked message when `mode=edit` is forced.
6. **Extras** — Items with `is_extra` show the “Extra add-on” chip; shop CTA appears when extras feature is enabled and editing is allowed.
7. **Empty box** — Empty state copy and “Browse shop extras” when applicable.
8. **API unchanged** — PATCH quantity and DELETE item still return JSON with `estimated_value` only (no regression).

## Automated coverage

- `tests/Feature/Box/BoxControllerTest.php`

## Behaviour notes

- **Budget:** `BudgetRemainderService::getBudgetDetails()` uses the latest active subscription and `config('box.included_value_by_size')`; `getRemainder()` delegates to it.
- **`app.boxes.current`:** Still redirects to `app.boxes.show` for the resolved week or dashboard with `current-box-missing`.
