# Data-flow walkthrough — follow-ups

End-to-end walkthrough on `feature/data-flow-walkthrough`: created 3 producers + 4 products via the admin UI, ran the full Wochen-Katalog → Box-Setup → Deliveries → Customer-side → Delivery flow → Payment charge sequence with one real subscriber (Maria). Eight real bugs surfaced; some fixed inline, the rest documented here.

## High-priority bugs

### Box-Setup → no auto-deliveries

**Symptom:** Admin clicks `Boxen speichern`, gets "Boxen für 1 Abo(s) gespeichert.", but no `deliveries` rows are created. Customer dashboard shows the box in Almanach but `Lieferzeitplan` is empty.

**Cause:** `WeeklyBoxSetupService` creates `boxes` rows but doesn't fire `DeliveryProjector::projectForBox()`. Delivery generation lives in a separate command (`deliveries:sync-from-boxes`) + an admin button (`Lieferungen aus Boxen synchronisieren`). Probably also runs daily via cron, but for the same-day setup case the customer sees no scheduled delivery.

**Fix shapes:**
- (a) Hook the projector inside `WeeklyBoxSetupService::compose()` so saving boxes always projects deliveries in one transaction.
- (b) Keep them separate, but make the admin "Boxen speichern" button also POST to the sync endpoint (chained UX).
- (c) Show a banner on Box-Setup if there are boxes-without-deliveries: "12 boxes have no delivery — sync now?"

### `deliveries` table missing `in_transit_at` / `delivered_at`

**Symptom:** When admin flips status `geplant → in_transit → delivered` via the inline dropdown, no timestamps are recorded — only the current `status` enum updates. Support questions like "when was delivery #4 marked delivered?" can't be answered without log scraping.

**Fix:** migration to add nullable `in_transit_at` / `delivered_at` timestamp columns, plus controller logic to stamp them on transition (already prepared but reverted in this PR because the columns don't exist yet).

```php
Schema::table('deliveries', function (Blueprint $t): void {
    $t->timestamp('in_transit_at')->nullable()->after('status');
    $t->timestamp('delivered_at')->nullable()->after('in_transit_at');
});
```

Add timestamping logic in `AdminDeliveryController::updateStatus` and `update` (existing draft in this branch's reverted diff).

### Per-user locale persistence missing

**Symptom:** Queued mailables render in `app.locale` (the .env value) regardless of the recipient's preferred language. Maria, a German user, would have received "Your Erdgut box is on the way" instead of "Deine Erdgut-Kiste ist unterwegs" if the .env had `APP_LOCALE=en` (which the local skeleton did — fixed locally).

**Fix:**
- Migration: add `locale` column to `users` table (`string('locale', 5)->default('de')`).
- Implement `Illuminate\Contracts\Translation\HasLocalePreference` on `User` model:
  ```php
  public function preferredLocale(): string {
      return $this->locale ?? config('app.locale');
  }
  ```
- Set the locale during onboarding from the active session (the `SetLocale` middleware already detects request locale).
- Now `Mail::to($user)->queue(...)` will render in the user's locale automatically.

## Medium-priority bugs

### Bioland Sonnenfeld has no valid product category

**Symptom:** Categories dropdown is hardcoded to `vegetables / fruit / dairy / bakery / fish / meat`. An apiary producer (honey) has no valid mapping. Same problem for condiments, oils, eggs (we have an eggs add-on but no eggs category).

**Fix:** Either expand the `ProductCategory` enum to add `honey`, `eggs`, `condiments`, `oils`, OR drop categories entirely and use the `dietary_tags` / freeform tags pattern. The current set is a partial mismatch.

### Customer-facing "Status: BEARBEITBAR" leaks an admin term

**Symptom:** In the dashboard's Almanach section, an upcoming box shows `Status: BEARBEITBAR`. "Bearbeitbar" / "editable" is a meaningful internal state (the box can still be modified before the lock date) but doesn't translate to anything the customer needs to know.

**Fix:** Map admin status values → customer-facing labels in the status badge component:
- `editable` → "In Vorbereitung" / "Wird zusammengestellt"
- `locked` → "Steht fest" / "Wird zugestellt"
- `delivered` → "Geliefert"

Or hide the status row entirely on customer views and only show on admin views.

### Box rows in Almanach have no click-through

**Symptom:** "Woche vom 06. Mai 2026 — Status: BEARBEITBAR — Geschätzter Wert: EUR 24,20" is plain text. Customers can't click to see what's in the box.

**Fix:** Wrap the row in a `<a href="{{ route('app.boxes.show', $box) }}">` so the customer can preview contents.

### Currency formatting still inconsistent

**Symptom:** Almanach renders "EUR 24.20" (period decimal). Other places use "24,20 €". The Almanach's `Estimated value: EUR :amount` translation key uses `number_format($v, 2)` which defaults to a period.

**Fix:** Same as the admin-side currency follow-up — adopt a single helper (`Money::formatEur()` or `@eur` Blade directive) and use it everywhere.

### Admin Lieferungen sort order arbitrary

**Symptom:** Rows in the deliveries table appear in order 2, 1, 3, 4 — neither chronological nor ID-based.

**Fix:** `AdminDeliveryController::index` should `orderBy('scheduled_for_date', 'asc')` so admins see deliveries in upcoming-first order.

### Box-Setup target/max labels need explanation

**Symptom:** "Ziel: 24,50 € / Max: 35,00 €" — admins seeing this for the first time wonder why the target is below the customer's price. Math is correct (target = price × (1 − margin_rate)) but unexplained.

**Fix:** Either change labels to `Produktwert (Soll): 24,50 € · Kundenpreis: 35,00 €`, or add a `?` icon with a tooltip explaining the 30% margin.

## UX polish

### Lieferzeitplan formal-pronoun bleed

`Wenn Ihr Abo aktiv ist, erscheinen Ihre Lieferwochen hier. Sie können dennoch Standardwerte für den nächsten Zyklus festlegen.` — should be informal. Belongs in the global formal→informal pass.

### Producer index `Bearbeiten / Löschen` action column

Inline text-link styling. The customer-facing redesign adopted filled `btn-danger` for destructive actions. Keep the inline version for table density, but apply the new red filled style for emphasis.

### Categories dropdown sort

Order is whatever `Product::categories()` returns. Sort alphabetically (using the localized label, not the enum value) for find-ability.

## Not yet walked

- **Per-product image upload** — the form has `image` and `gallery_images[]` fields; not exercised in this walkthrough.
- **Inline "Neues Produkt anlegen"** in Wochen-Katalog — the `▶ Neues Produkt anlegen` toggle in each row supports adding a brand-new product without leaving the catalog page. Untested.
- **Box-detail page** (`/app/admin/boxes/{box}`) — the box composition editor for a single box.
- **Trial order flow** — separate from subscription; has its own controller + checkout.
- **Refund flow** — admin payment detail has refund actions.
- **Audit log surface** — `audit_events` table is being written but no UI exposes it.
- **Real SumUp end-to-end** — the walkthrough hit the validation-error path because the seeded token is fake. Confirming a successful charge requires walking the SumUp widget with a real test card.
