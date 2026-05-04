# Admin-flow walkthrough — follow-up items

Captured 2026-05-04 during a top-to-bottom code-walk of the operational admin surfaces (producers → products → weekly catalog → boxes → deliveries → payments → subscriptions → orders → billing → audit-events). Companion to `admin-ux-walkthrough-followups.md` but scoped to operational ergonomics rather than visual design.

> **Status as of 2026-05-04:** 6 quick-wins shipped (`fix/admin-quick-wins-batch`); 5 medium items shipped (`feature/admin-medium-batch`: #1 box-setup draft persistence, #10 dynamic timeslot dropdown, #13 bulk mark-paid, #14+#15 box-detail eggs/stock warnings, #25 spargel refund pattern mirrored on payments). Each `### ` heading carries its own status marker. Smoke-test verified all admin pages render 200 OK as admin — no undefined-variable / null-call crashes.

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

### #8 — Subscriptions index `next_charge_date` shows date only, no time

**What:** Display is `toDateString()` so admin can't tell if the charge fires at 00:00 today (already happened) or 23:59 (still upcoming).
**Where:** `resources/views/admin/operations/subscriptions/index.blade.php:84`
**Why it matters:** Rescheduling decisions get made on incomplete info; potential double-charges or missed reschedules.
**Suggested fix:** Show "06:00" suffix matching the cron schedule (or pull configured charge time from a service); or add a tooltip showing exact charge time.

### #9 — "Missing payment" filter doesn't sum total outstanding

**What:** Filter shows N subs missing payments but doesn't aggregate total amount due — admin clicks N rows to know if it's €50 or €5000 outstanding.
**Where:** `resources/views/admin/operations/subscriptions/index.blade.php:1-3, 23-27`
**Why it matters:** Accountancy-grade summary information missing; estimating cash gap is manual.
**Suggested fix:** Add a summary line below the filter row: "Total outstanding: €XXX,XX over N subscriptions."

### ~~#10 — Order schedule form: timeslot dropdown not filtered by selected date~~ ✓ shipped (`feature/admin-medium-batch`)

Date + timeslot are now reactive: picking a past date or today-after-the-slot-already-passed filters the slot dropdown to only viable options, the submit button disables itself when no slots are valid, and a localised weekday hint renders next to the date so the admin can see which weekday they've picked. Server-side validation untouched — this just stops admins submitting combinations that would bounce.

### #11 — Trial order box-setup link only appears after scheduling

**What:** Order detail shows a "Schedule first" badge until scheduled, then *after* scheduling a separate "Setup box contents" link materializes elsewhere on the page. Two-step UX with no inline hint.
**Where:** `resources/views/admin/orders/show.blade.php:54-62`
**Why it matters:** Admin schedules and then has to scroll to find the box-setup link, doesn't intuitively know it'll appear.
**Suggested fix:** Inline a note in the schedule form: "After scheduling, the 'Setup box contents' link will appear above."

### #12 — Refund form lacks validation hints

**What:** `refund_amount` field has no visible constraint or hint. Partial refund supported? Must match payment amount? Defaults to full refund?
**Where:** `resources/views/admin/operations/payments/show.blade.php` (refund form)
**Why it matters:** Admin tries partial refund, gets silent validation error or unexpected SumUp behavior.
**Suggested fix:** Add `max="{{ $payment->amount }}"`, placeholder showing the full amount, and help text: "Leave blank to refund the full amount."

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

### #19 — Delivery list shows status badge but no last-status-change timestamp

**What:** Status column shows the badge but doesn't show when that status was set. Admin can't distinguish "delivered 1 hour ago" from "delivered 1 week ago" without clicking through.
**Where:** `resources/views/admin/operations/deliveries/index.blade.php` (status column)
**Why it matters:** Operational triage decisions get harder; "is this delivery stuck?" requires extra clicks.
**Suggested fix:** Add a tooltip on the status badge or a thin secondary line showing `in_transit_at` / `delivered_at` / `failed_at` depending on current state, formatted via the new `<x-admin-datetime>` (see #6).

### #20 — Orders index missing `delivered_at` column

**What:** Index shows `created_at` and `scheduled_for_date` but not `delivered_at` — "which trial orders shipped last week?" requires N detail clicks.
**Where:** `resources/views/admin/orders/index.blade.php` (orders table)
**Why it matters:** Common operational query; should be a column.
**Suggested fix:** Add a "Delivered" column with `delivered_at` formatted, or a single combined "Last activity" column showing whichever timestamp is most recent (created < scheduled < paid < delivered).

---

## 🟢 Polish / operational ergonomics

Lower-priority. Won't bite anyone immediately but worth a sweep when convenient.

### #21 — Producers + Products index: no `Created at` / `Updated at` column

**What:** Tables don't show timestamps, so admin can't audit recent additions or spot stale test data.
**Where:** `resources/views/admin/producers/index.blade.php:95-131`, `resources/views/admin/products/index.blade.php:144-210`
**Suggested fix:** Add a "Last updated" column with relative time ("vor 2 Tagen"), or add a "Sort by newest" toggle.

### #22 — Pagination shows page count but no row count

**What:** Both producer and product index paginate at 24/page but no "Showing 1–24 of N" indicator.
**Where:** Same files as #21
**Suggested fix:** Add total-count display in the table caption or near pagination controls.

### #23 — Product gallery alt-text inputs only on edit

**What:** The gallery alt-text inputs render with `@if ($isEdit && $product->images->isNotEmpty())` so they're absent on create. Admin uploading images on create can't add alt text without re-editing.
**Where:** `resources/views/admin/products/_form.blade.php:381-399`
**Suggested fix:** Allow alt-text entry on create, or surface a clear note: "Add alt text after creating the product."

### #24 — Producer form has two image fields without clear hierarchy

**What:** Both an upload field and an external URL field. Help text mentions the URL is fallback but the labels don't make this clear.
**Where:** `resources/views/admin/producers/_form.blade.php:110-114`
**Suggested fix:** Rename the URL field to "External image URL (fallback)" and update its help text to explicitly say "Used only when no file is uploaded above."

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
