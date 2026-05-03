# Data-flow walkthrough — follow-ups

End-to-end walkthrough on `feature/data-flow-walkthrough`: created 3 producers + 4 products via the admin UI, ran the full Wochen-Katalog → Box-Setup → Deliveries → Customer-side → Delivery flow → Payment charge sequence with one real subscriber (Maria). Eight real bugs surfaced; some fixed inline, the rest documented here.

## High-priority bugs

### Box-Setup → no auto-deliveries — ✓ shipped

**Verified 2026-05-03:** `WeeklyBoxSetupService::applyDistribution()` and `applyPerCustomerDistribution()` both call `$this->deliveryProjector->projectForBox($box->fresh())` after each box save. Idempotent via `updateOrCreate` on `(user_id, scheduled_for_date)`.

### `deliveries` table missing `in_transit_at` / `delivered_at` — ✓ shipped

**Verified 2026-05-03:** Both columns exist in `deliveries`, the model casts them to `datetime`, and `AdminDeliveryController::updateStatus`/`update` stamp them on the `geplant → in_transit → delivered` transitions (with backfill logic when admins skip a step).

### Per-user locale persistence missing — ✓ shipped

**Verified 2026-05-03:** `users.locale` column exists (migration `2026_04_27_000002_add_locale_to_users.php`), `User` implements `HasLocalePreference`, `preferredLocale()` falls back to `config('app.locale', 'de')`. Queued mailables now render in the recipient's locale automatically.

## Medium-priority bugs

### Customer-facing "Status: BEARBEITBAR" leaks an admin term — ✓ shipped

`box_status_customer.editable/locked/delivered` lang keys exist in de/en/fr ("In Vorbereitung" / "Wird zugestellt" / "Geliefert") and the dashboard Almanach panel uses them via `__('box_status_customer.'.$box->status->value)`. Falls back to no-status-row when the key resolves to itself (i.e. unknown enum cases).

### Box rows in Almanach have no click-through — ✓ shipped

Each Almanach row in `dashboard.blade.php` is wrapped in `<a href="{{ route('app.boxes.show', $box) }}">` with chapter-produce focus ring.

### Currency formatting still inconsistent — ✓ shipped

PR #51 swept every currency render site onto `App\Support\Money::format` + the `@money` / `@eur` / `@eurCents` Blade directives, including the Almanach.

### Admin Lieferungen sort order arbitrary — ✓ shipped

`AdminDeliveryController::index` orders by `('scheduled_for_date')->orderBy('id')` (verified 2026-05-03).

### Box-Setup target/max labels need explanation — ✓ shipped

`admin.box_setup.target_max_explanation` lang key exists ("Ziel = Kundenpreis × (1 − Planungs-Marge)…") and is rendered as a `title` tooltip on the target/max labels in `boxes/setup.blade.php` and `boxes/show.blade.php`.

## UX polish

### Lieferzeitplan formal-pronoun bleed — ✓ shipped

The string is now informal: *"Wenn dein Abo aktiv ist, erscheinen deine Lieferwochen hier. Du kannst dennoch Standardwerte für den nächsten Zyklus festlegen."*

### Producer index `Bearbeiten / Löschen` action column — ✓ shipped

Delete uses `btn-danger-sm`, Edit uses brand-ink with underline-on-hover (small-polish-batch 2026-05-03).

### Categories dropdown sort — ✓ shipped

`ProductCategory::casesSortedByLabel()` + `valuesSortedByLabel()` exist and are used by `AdminProductController` for both filter + form dropdowns. Sort respects locale via `Collator`.

## Not yet walked

- **Per-product image upload** — the form has `image` and `gallery_images[]` fields; not exercised in this walkthrough.
- **Inline "Neues Produkt anlegen"** in Wochen-Katalog — the `▶ Neues Produkt anlegen` toggle in each row supports adding a brand-new product without leaving the catalog page. Untested.
- **Box-detail page** (`/app/admin/boxes/{box}`) — the box composition editor for a single box.
- **Trial order flow** — separate from subscription; has its own controller + checkout.
- **Refund flow** — admin payment detail has refund actions.
- **Audit log surface** — `audit_events` table is being written but no UI exposes it.
- **Real SumUp end-to-end** — the walkthrough hit the validation-error path because the seeded token is fake. Confirming a successful charge requires walking the SumUp widget with a real test card.
