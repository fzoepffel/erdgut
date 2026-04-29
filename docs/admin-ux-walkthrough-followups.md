# Admin-side UX walkthrough — follow-up items

Companion to `feature/admin-ux-walkthrough` PR. Items deferred during the browser walkthrough that warrant their own batch.

## Architectural

### Admin design language: separate component set or merge with customer? (~discussion)

Admin currently uses raw Tailwind utility classes (`bg-emerald-600`, `bg-gray-900`, `bg-amber-500`) inline in views, while customer area uses the `<x-primary-button>` / `<x-secondary-button>` / `<x-danger-button>` components backed by `btn-solid` / `btn-ghost` / `btn-danger` CSS classes.

This creates three problems:
1. **Color drift**: every new admin button is a new Tailwind palette decision. Buttons like "Produzent anlegen" are emerald, "Bestellung anlegen" are gray-900, "Lieferungen aus Boxen synchronisieren" are amber. No system.
2. **Refactor friction**: changing a button style means find-and-replace across 20+ inline class strings vs one CSS class.
3. **Brand consistency**: admin doesn't *need* to look like the customer area, but the disconnect is jarring when an admin clicks through to a customer-side detail page.

**Two paths:**
- (A) **Adopt the customer button components in admin**: replace inline classes with `<x-primary-button>` / `<x-secondary-button>` / `<x-danger-button>`. ~1-2 days. Visually unifies; loses the "elevated admin surface" vibe that emerald-green currently signals.
- (B) **Build admin-specific button components**: `<x-admin-primary-button>` etc. with their own CSS classes. Same effort, preserves the visual distinction.

Defer until you have a stronger position on whether admin is a "different brand surface" or "same brand, different audience".

### Inline status enum-bleed audit (~2h)

The walkthrough caught the obvious tables (Abos / Anstehende Abbuchungen / Lieferungen) but the codebase still has more `{{ $model->status->value }}` / `{{ $model->plan->value }}` instances. Audit:

```bash
grep -rn '\$.*->status->value\|\$.*->plan->value\|\$.*->box_size }}' resources/views/admin/
```

Each hit is either:
- A status badge → swap for `<x-status-badge :value="$model->status" type="..." />`
- A plan label → swap for `__('plan.'.$model->plan->value)`
- A box size label → swap for `__(ucfirst($model->box_size))`

### ~~Currency formatting helper~~ ✓ shipped (PR #51)

`App\Support\Money` now exposes three entry points:
- `formatEur($amount)` → `"12,50 €"` (canonical German display)
- `formatCents($cents)` → same, from integer cents
- `format($amount, $decimals = 2)` → bare `"12,50"` for use as the `:amount` placeholder in translation strings that bake `EUR` / `€` into the template

Three Blade directives (`@eur`, `@eurCents`, `@money`) wrap them.

PR #51 swept every currency render site (customer onboarding, dashboard, payments history, marketing home, both transactional emails, admin dashboard, box budget panel, subscriptions edit/create) onto the helper. Quantity calls (kg / pcs in dashboard, weekly catalog, box detail) intentionally left as raw `number_format` — they're not currency.

### Top-bar search & quick-jump (~3h)

The admin sidebar covers navigation but typing "/" or `Cmd+K` to open a search palette would speed up daily use. Common targets: "user by email", "subscription by ID", "delivery by date". Shows up later in lifetime; flag now so it's not forgotten.

### Audit log / events viewer (~1 day)

The redesign added `audit_events` table (Batch H equivalent on subscription side). No admin UI to view it. A "Verlauf" tab on user/subscription/delivery detail pages showing recent events would be high-leverage for support work.

## Per-page leftover polish

### Übersicht (`/app/admin`)

- **`Registrierte Nutzer 3` + `E-Mail verifiziert 3`** are still two cards even though both show the same number. Either:
  - Merge to one card "3 Nutzer (alle verifiziert)" with conditional sub-line for unverified count
  - Keep separate but show "verified" only when there's a discrepancy worth flagging
- **Trial-pipeline panel** has 4 cards but they all show `0` for new installs. Empty state per card could be more useful — e.g., "Keine Bestellungen mit ausstehender Zahlung" instead of "0".

### Produzenten / Produkte / Nutzer (catalog tables)

- **Empty states are very plain** — "Noch keine Produzenten." with no CTA. New install onboarding for admins could be friendlier (animated arrow at the "Neu anlegen" button, sample-data import, etc.).
- **No breadcrumbs** anywhere in admin. Sidebar gives location but breadcrumbs help when drilling 3+ levels deep (Produkt > Wochen-Katalog > Bestand).

### Wochen-Katalog (`/app/admin/products/weekly`)

- **Empty state confusion**: shows "Noch keine Produkte für diese Woche hinterlegt." then immediately renders 3 empty input rows. Either-or, not both.
- ~~`REST` column header~~ ✓ already says `Verbleibend` in lang/de.json (`admin.weekly_catalog.col_remaining`); doc was outdated.
- ~~`LÖSCHE...` truncated~~ ✓ already says `Löschen` in full (`admin.weekly_catalog.col_delete`); the truncation was a CSS width artifact in an older walkthrough screenshot.
- **`Bestehend` dropdown values** — first column of input rows. Categorizes the row (existing product vs new product?) but the label doesn't make that obvious. Worth a short hint or tooltip.

### Lieferungen

- **Status setzen inline dropdown** still doesn't have an obvious "save" button — relies on `onchange` auto-submit (or does it?). Verify the UX is intentional and not silently failing.
- **`Lieferungen aus Boxen synchronisieren` button is amber** — different from black `Neue Lieferung` next to it. Same row, two colors. Worth normalizing once the design-language question is settled.

### Box-Setup

- **Rows in the compact grid table aren't visually clickable** — admin probably doesn't realize each row links to that week's setup view. Add `cursor-pointer` + a hover row-bg + an explicit `<a>` wrap so the whole row is the link.
- **The bottom 8-week card grid** duplicates the table data. Could collapse one or the other depending on which is more useful per-admin (instrument click destinations to decide).

### Zahlungsversuche

- **Vertical filter form** still vertical even on desktop. Could be a horizontal grid (4 fields per row) for compactness on wide viewports. Saves ~3 viewport heights.
- **No CSV export** of the filtered payments list. Common admin need (reconciliation, accounting, etc.). Add a `?format=csv` query param + a download button.

### Anstehende Abbuchungen

- Page is fine post-fixes. Could add a "send a reminder email" per-row action, but that's a feature not polish.

### Producer / Product detail forms

- **No required-field indicators** on the create/edit forms. Same fix as customer onboarding (`required` / `optional` props on `<x-input-label>`) — but admin uses raw `<label>` tags. Either migrate admin to the same component, or apply asterisks inline.
- **Translation panels (DE/EN/FR) take significant vertical space** — could become tabs (DE | EN | FR) instead of stacked. Smaller form, same content.
- **`Übersetzungen — Alle drei Sprachen sind erforderlich.`** is good messaging but the visual treatment is a tiny grey caption right of the heading. On smaller viewports it might wrap awkwardly.

### Nutzer

- **No way to promote/demote users** to/from admin. Currently you can only create new admins via the form. If a customer needs to become an admin, you have to ask them to re-register.
- **Admin row's "Löschen" button is greyed** — good (you can't delete yourself). Should also block deleting the last admin to avoid lockout.

## Not yet walked

- **Producer/product create + edit forms in detail** (just glanced)
- **Box-setup detail view** (per-week edit page; the planner overview was walked, the per-week was not)
- **Box-setup compact grid week-link click flow**
- **Admin payment detail** (`/app/admin/payments/{id}`) — refund flow, mark-paid-externally
- **Admin order detail** (`/app/admin/orders/{id}`) — schedule/compose/mark-delivered actions
- **Trial box setup page** (`/app/admin/operations/trial_boxes/setup`)
- **Billing coverage page** (`/app/admin/billing/coverage`)
- **Admin user impersonation** (does it exist? Useful for support)
- **Admin email-resend buttons** (we added these in Batch D — verify they all render correctly and trigger the right Mailable)
