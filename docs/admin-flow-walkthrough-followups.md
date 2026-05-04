# Admin-flow walkthrough — follow-up items

Captured 2026-05-04 during a top-to-bottom code-walk of the operational admin surfaces (producers → products → weekly catalog → boxes → deliveries → payments → subscriptions → orders → billing → audit-events). Companion to `admin-ux-walkthrough-followups.md` but scoped to operational ergonomics rather than visual design.

> **Status as of 2026-05-04:** 6 quick-wins shipped (`fix/admin-quick-wins-batch`); 5 medium items shipped (`feature/admin-medium-batch`: #1 box-setup draft persistence, #10 dynamic timeslot dropdown, #13 bulk mark-paid, #14+#15 box-detail eggs/stock warnings, #25 spargel refund pattern mirrored on payments); 9 small polish items shipped (`feature/admin-small-polish-batch`: #8 next_charge_date time, #11 trial setup-link hint, #12 refund hints, #19 delivery status timestamp, #20 orders delivered_at column, #21 producers/products timestamps, #22 pagination row count, #23 product alt-text on create, #24 producer image hierarchy). Each `### ` heading carries its own status marker. Smoke-test verified all admin pages render 200 OK as admin — no undefined-variable / null-call crashes.

---

## 🔴 Bugs / data-integrity concerns

These can either lose data, lead to silent state drift, or let an operator ship wrong info to customers. Should be picked first.

### ~~#1 — Box-Setup composition lives in memory only, no warning on refresh~~ ✓ shipped (`feature/admin-medium-batch`)

`beforeunload` confirmation now fires whenever the Alpine state is dirty, and editable composition (sizes + customer lines + eggs lines + ok flags) is snapshotted to `localStorage` on every change keyed by week. On revisit, an opt-in "Restore unsaved changes" banner offers apply / discard — never auto-overwrites server-resolved state, since composition can shift between visits.

### #2 — Box composition fingerprint goes stale on out-of-flow edits

**What:** The fingerprint logic is meant to flag "this composition has changed since last admin approval", but if a box is edited via the per-box detail page (`/app/admin/boxes/{box}`) instead of the setup flow, the fingerprint isn't refreshed. The next setup-render may auto-approve unchanged-looking diffs.
**Where:** `app/Http/Controllers/Admin/AdminBoxSetupController.php` (fingerprint computation), `app/Http/Controllers/Admin/AdminBoxController.php` (out-of-flow editor)
**Why it matters:** Composition can change without explicit admin re-approval — undermines the audit-trail value.
**Suggested fix:** Clear fingerprint (set to null) on any box mutation outside of the setup flow; force re-fingerprint on next setup render.

### #3 — Eggs add-on refund isn't reversed when admin re-adds eggs

**What:** When the admin removes the eggs item from a box, `EggsUnfulfilledHandler` fires a refund. If the admin then re-adds eggs to the same box, no compensating debit is issued — the customer gets eggs AND keeps the refund.
**Where:** `app/Http/Controllers/Admin/AdminBoxController.php:268–283`
**Why it matters:** Silent ledger drift; customer lifetime value misreported.
**Suggested fix:** Either (a) add a "refund reversal" / re-charge flow when eggs are re-added after `eggs_addon_unfulfilled_at` is set, or (b) document the invariant as "removing eggs is a one-way decision" and gate the re-add behind a confirmation modal.

### ~~#4 — Bulk delivery delay accepts negative `delta_days`~~ ✓ verified already protected (false positive)

`AdminDeliveriesBulkDelayRequest.php:21` already has `'delta_days' => ['required', 'integer', 'min:1', 'max:30']`. The `<input type="number">` also has `min="1" max="30"` (HTML5 client-side). Two-layer protection. The agent's claim was wrong.

### ~~#5 — Orphan-deliveries cleanup uses translated `confirm()` with no hardcoded fallback~~ ✓ shipped (`fix/admin-quick-wins-batch`)

Now uses `Js::from(__(...) ?: 'Delete all orphaned deliveries? This cannot be undone.')` so an empty/missing translation key never silently bulk-deletes.

---

## 🟡 UX gaps that bite during operations

Workable bugs but not data-loss; each is a real friction point that costs minutes per occurrence.

### #6 — Date format chaos across admin views

**What:** Three different formats appear on the same admin pages: `d M Y H:i`, `d.m.Y H:i`, `Y-m-d H:i`, `D d M Y`. Forces mental parsing every time the admin scans for a recent event.
**Where:** `resources/views/admin/orders/show.blade.php:35,36,122-124` + ~50 other call sites
**Why it matters:** Admin scans dates fast in operations; mixed formats slow every comparison.
**Suggested fix:** Build `<x-admin-datetime :value="..." />` Blade component using `d.m.Y H:i` (German-friendly, matches `boxes/show.blade.php:111`); sweep all admin call sites in one PR.

### ~~#7 — Payments status filter shows raw enum values~~ ✓ shipped (`fix/admin-quick-wins-batch`)

Now reads from `admin.operations.payment_status.{pending|paid|failed|refunded}` keys (added across de/en/fr). Drive-by also fixed delivery status filter dropdown which had the same untranslated-labels bug — added `admin.deliveries.status.in_transit` and `admin.deliveries.status.failed` translations that were missing across all 3 locales.

### ~~#8 — Subscriptions index `next_charge_date` shows date only, no time~~ ✓ shipped (`feature/admin-small-polish-batch`)

The next-charge column now renders the date plus a small `06:00` suffix (matching the configured `subscriptions:charge` cron). The label is sourced from a new `config('payments.recurring_charge_time_label')` so the schedule + display can drift in lockstep if the cron moves.

### #9 — "Missing payment" filter doesn't sum total outstanding

**What:** Filter shows N subs missing payments but doesn't aggregate total amount due — admin clicks N rows to know if it's €50 or €5000 outstanding.
**Where:** `resources/views/admin/operations/subscriptions/index.blade.php:1-3, 23-27`
**Why it matters:** Accountancy-grade summary information missing; estimating cash gap is manual.
**Suggested fix:** Add a summary line below the filter row: "Total outstanding: €XXX,XX over N subscriptions."

### ~~#10 — Order schedule form: timeslot dropdown not filtered by selected date~~ ✓ shipped (`feature/admin-medium-batch`)

Date + timeslot are now reactive: picking a past date or today-after-the-slot-already-passed filters the slot dropdown to only viable options, the submit button disables itself when no slots are valid, and a localised weekday hint renders next to the date so the admin can see which weekday they've picked. Server-side validation untouched — this just stops admins submitting combinations that would bounce.

### ~~#11 — Trial order box-setup link only appears after scheduling~~ ✓ shipped (`feature/admin-small-polish-batch`)

When the trial order isn't scheduled yet, the schedule form now inlines a small emerald hint explaining that the "Setup box contents" link will appear above on this page after saving.

### ~~#12 — Refund form lacks validation hints~~ ✓ shipped (`feature/admin-small-polish-batch`)

The amount input already had `min`/`max` and a default value; we added a placeholder showing the full amount and a help line under the field: "Maximum :amount EUR — pre-filled with the full payment amount, change it for a partial refund."

### ~~#13 — No bulk mark-paid-externally action on payments index~~ ✓ shipped (`feature/admin-medium-batch`)

Row checkboxes (only on bulk-eligible rows = pending / failed) + a header "select all eligible on page" toggle + a sticky bulk action bar that prompts for a single shared external-reference and submits to a new `payments.bulk-mark-paid-externally` route. Already-paid / refunded rows that sneak into the payload are silently skipped; the flash message reports `X marked, Y skipped`. Each updated row writes its own audit event with `metadata.bulk = true` so post-hoc grouping by `external_reference` works.

### ~~#14 — Eggs-missing warning shown on setup but not on box detail~~ ✓ shipped (`feature/admin-medium-batch`)

Persistent chapter-meat banner now renders at the top of `boxes/show.blade.php` whenever the customer paid for the eggs add-on and the box has zero eggs lines. Driven by Alpine reactivity — adding an eggs line clears the warning live without a reload.

### ~~#15 — Out-of-stock indicator only in dropdown, not on inline product card~~ ✓ shipped (`feature/admin-medium-batch`)

Persistent chapter-meat "Außerhalb Woche" badge now renders next to the product name on every line whose product is flagged `missing_stock=true`, with a tooltip explaining the admin should review or replace it. New `productMissingStock(pid)` accessor on the Alpine state mirrors the existing `productIsSafe(pid)` pattern.

### ~~#16 — Products `is_available` and `is_published` default to `true` on create~~ ✓ shipped (`fix/admin-quick-wins-batch`)

Both flags now default to `false` on create. Operators must explicitly tick them once the product is fully filled out. Edit-flow behaviour unchanged (preserves whatever's stored on the row).

### ~~#17 — Translation-tab error indicator is a 2×2px red dot~~ ✓ shipped (`fix/admin-quick-wins-batch`)

Tabs with validation errors now render with rose-600 border + rose-50 background tint + a 16×16 rounded-full badge with "!" inside, instead of the easy-to-miss 2px dot. Applied to both products and producers forms. Auto-focus on the failing tab is still TODO (deferred to a later batch).

### ~~#18 — Delivery status filter dropdown order is enum-declaration order, not workflow order~~ ✓ verified already correct (false positive)

`DeliveryStatus` enum cases ARE declared in workflow order: `Scheduled → InTransit → Delivered → Skipped → Failed`. `cases()` preserves declaration order. The agent's claim was wrong. (Drive-by fix from #7 did add missing translations for the labels.)

### ~~#19 — Delivery list shows status badge but no last-status-change timestamp~~ ✓ shipped (`feature/admin-small-polish-batch`)

The status cell now renders a small relative-time line under the badge (`in_transit_at` / `delivered_at` / `updated_at` fallback), with the absolute timestamp surfaced via the `title` tooltip. Failed/skipped/scheduled fall back to row `updated_at` since there's no per-state timestamp column for those.

### ~~#20 — Orders index missing `delivered_at` column~~ ✓ shipped (`feature/admin-small-polish-batch`)

Added a "Delivered" column between Scheduled and City. Renders `d M H:i` when set, em-dash when not. Empty-state colspan bumped from 8 to 9.

---

## 🟢 Polish / operational ergonomics

Lower-priority. Won't bite anyone immediately but worth a sweep when convenient.

### ~~#21 — Producers + Products index: no `Created at` / `Updated at` column~~ ✓ shipped (`feature/admin-small-polish-batch`)

Both indexes now have a "Zuletzt aktualisiert" column with relative time (`diffForHumans()`) and the absolute timestamp on hover via `title`.

### ~~#22 — Pagination shows page count but no row count~~ ✓ shipped (`feature/admin-small-polish-batch`)

Both indexes now render "Zeige :from–:to von :total" beside the pagination links. Hidden when the table is empty (firstItem/lastItem default to 0).

### ~~#23 — Product gallery alt-text inputs only on edit~~ ✓ shipped (`feature/admin-small-polish-batch`)

Took the simpler route: kept the alt-text inputs gated to edit (matches how `gallery_remove` checkboxes work), but added an inline help line under "Add images" on the create form explaining alt text is added in edit view after saving. Avoids juggling temporary IDs in the create-time payload.

### ~~#24 — Producer form has two image fields without clear hierarchy~~ ✓ shipped (`feature/admin-small-polish-batch`)

Reordered the producer form so upload comes first ("Uploaded image (primary)"), with the URL underneath labelled "External image URL (fallback)" and help text "Used only when no file is uploaded above." Order in markup now mirrors precedence so admins don't fill both.

### ~~#25 — Refund audit trail doesn't differentiate success from failed-attempt-recorded for subscription/order payments~~ ✓ shipped (`feature/admin-medium-batch`)

`AdminPaymentController::refund()` now mirrors the spargel refund pattern: try SumUp first, only flip local status to `refunded` after the provider confirms. On rejection we log `refund_attempt_failed` (not `refund`), record the attempt count + last error on the payment row (new `refund_attempts` / `last_refund_attempt_at` / `last_refund_error` columns), and leave the status as `paid` so the admin doesn't see a misleading green badge. The detail page surfaces a persistent rose banner with the attempt count + last error whenever `refund_attempts > 0` and status is still paid.

---

## Suggested batches

**Quick wins (~1–2h each — combine into one PR):**
- #4 (delta_days validation), #5 (confirm fallback), #7 (translate filter), #16 (publish defaults), #17 (tab error), #18 (status order)

**Medium (~half-day each):**
- #1 (box-setup unsaved-changes warning + draft persistence) — highest data-loss risk, do first
- #13 (bulk mark-paid)
- #10 (timeslot filtering)
- #14, #15 (eggs/stock warnings on box detail)
- #25 (mirror spargel refund pattern in subscription/order refunds)

**Bigger (~1 day each, want product-level call before doing):**
- #2 (fingerprint refresh strategy)
- #3 (eggs refund-reversal flow)
- #6 (date format component sweep across ~50 call sites)
- #9 (outstanding-total summary)

**Optional polish (group #21-#24 into one small PR when convenient):**
- Index timestamps + count display + alt text + producer image fallback hierarchy
