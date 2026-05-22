# Erdgut redesign plan — ticket index

Companion index for [`erdgut-redesign-plan.csv`](./erdgut-redesign-plan.csv). The CSV is the source of truth for status and estimates. This file groups tickets by phase so the plan is scannable at a glance.

## Reference row

- **ERD-REDESIGN-00** — Full project context and locked decisions. Read this row first; everything below assumes those decisions.

## Phase 1 — Data backbone (foundation)

Migrations, enums, and pricing/audit scaffolding. Most Phase 2–8 tickets depend on rows here. **All schema + enum + pricing work shipped** in commit [`841d55c`](https://github.com/fzoepffel/erdgut/commit/841d55c); only the audit-logger call-site backfill remains.

- ERD-300 — Migration: `payment_mandates` table — **Done**
- ERD-301 — Migration: `boxes.subscription_id` + `preferences_snapshot` + `allergies_snapshot` — **Done**
- ERD-302 — Migration: `box_items.unit_price_snapshot` — **Done**
- ERD-303 — Migration: `deliveries.box_id` FK — **Done**
- ERD-304 — Migration: subscriptions extended fields (`address_id`, `delivery_weekday`, `preferred_timeslot`, `billing_mode`) — **Done**
- ERD-305 — Migration: `audit_events` table — **Done**
- ERD-307 — Migration: `product_images` table — **Done**
- ERD-308 — Migration: products catalog expansion (allergens, dietary tags, buying price, taste profile) — **Done**
- ERD-309 — Migration: `product_pairings` pivot — **Done**
- ERD-310 — Enums: Allergen, DietaryTag, TasteProfile, BillingMode + extend DeliveryStatus — **Done**
- ERD-311 — `BoxPricing` shared service (fixes trial €35 bug) — **Done**
- ERD-312 — `AuditLogger` service + backfill call sites — **Backlog** (service built; lifecycle-service call sites pending)
- ERD-348 — Trial pricing bug fix (uses ERD-311) — **Done**

> ERD-306 (subscription_skip_weeks migration) was **done** under the Phase 4 branch — no new ticket needed.
> Phase-2 follow-on `f8a3e4c` (SumUpMandateManager + billing_mode gating) is infrastructure that unlocks ERD-329 / ERD-330 but doesn't clear either on its own.

## Phase 2 — Unify code paths

Kill duplication, build reusable UI, fix SumUp UX.

- ERD-313 — Extract `WeeklyDistributionService` (with allergy/diet filtering)
- ERD-314 — Extract `DeliveryProjector`
- ERD-315 — Unify dashboard timeline
- ERD-316 — Reusable `<x-order-timeline>` + `<x-payment.sumup-widget>`
- ERD-345 — Redesign onboarding flow (reduces SumUp waiting friction)
- ERD-347 — Auto-filter composition by user allergies + dietary tags

## Phase 3 — Product catalog UX

All four tickets shipped in commits [`9de83f2`](https://github.com/fzoepffel/erdgut/commit/9de83f2) (admin form) and [`6e1d60f`](https://github.com/fzoepffel/erdgut/commit/6e1d60f) (customer + box views).

- ERD-317 — Admin product form (allergens, dietary, buying price, taste, pairings, image gallery) — **Done**
- ERD-318 — Customer product catalog (category logos, multi-image, taste chips, pairings) — **Done**
- ERD-319 — Box detail view (product images, allergen badges, pairings) — **Done**
- ERD-349 — Taste profile + "goes well with" pairings UI — **Done**

## Phase 4 — Customer flexibility

Most of this phase is **done** on [`feature/phase-4-customer-flexibility`](https://github.com/fzoepffel/erdgut/commits/feature/phase-4-customer-flexibility). Only the weekday/timeslot ticket remains.

- ERD-320 — P4.1 Pickup toggle — **Done**
- ERD-321 — P4.2 Preferred weekday + timeslot (zone-bounded) — **Backlog**
- ERD-322 — P4.3 Eggs/dairy toggle — **Done** (wired; follow-up audit line-item rolls into ERD-312)
- ERD-323 — P4.4 Skip-set UI — **Done**
- ERD-324 — P4.5 "Takes effect from week of X" copy — **Done**
- ERD-325 — P4.6 Apply address change to upcoming deliveries — **Done**
- ERD-326 — P4.7 One-month notice copy + soft disclaimer — **Done**
- ERD-327 — P4.8 Better empty-box guidance + admin `next_update_expected_at` — **Done**
- ERD-346 — Allergies as enum-driven selector — **Backlog**

## Phase 5 — Admin overrides (improvised-operations reality)

- ERD-328 — Admin creates trial on user's behalf
- ERD-329 — Admin creates subscription with `billing_mode=manual_invoice`
- ERD-330 — Admin "mark payment paid externally"
- ERD-331 — Admin reschedule delivery without cascading
- ERD-332 — Admin resend transactional email
- ERD-333 — Admin edit box after deadline with audit trail
- ERD-334 — Admin bulk "delay all deliveries on day X"
- ERD-335 — Bump `box_planner_weeks_ahead` to 12 + compact grid

## Phase 6 — Payment correctness

- ERD-336 — Trial → subscription defer `next_charge_date`
- ERD-337 — Admin refund action
- ERD-338 — Audit event on force-charge / manual-paid

## Phase 7 — Email

- ERD-339 — Missing transactional templates
- ERD-340 — Email deliverability (SPF/DKIM/DMARC + provider migration)
- ERD-341 — `docs/transactional-emails.md` matrix
- ERD-350 — Per-user email history view in admin (audit every Mailable, surface on user detail page) — **Done**

## Phase 8 — Polish + tests

- ERD-342 — E2E feature tests for stress-test flows
- ERD-343 — Accessibility baseline
- ERD-344 — Performance audit on 12-week planner

## Phase 9 — Dashboard rebuild (editorial-led)

Reorders the logged-in dashboard to match the editorial Lovable prototype while staying inside the existing Fraunces + Inter / cream-ink-moss visual system. Full plan and section specs in [`erdgut-dashboard-rebuild-plan.md`](./erdgut-dashboard-rebuild-plan.md). v1 is editorial-only (no admin UI for producer/recipe curation — those live in the follow-up tickets at the bottom).

- ERD-351 — §1 Hero band (two-column greeting + meta-framed illustration)
- ERD-352 — §2 Manifest grid + reusable `<x-customer.item-card>`
- ERD-353 — §3 Producer feature (landscape)
- ERD-354 — §4 Recipe feature (editorial-only)
- ERD-355 — §5 Almanac rows (replaces vertical card list)
- ERD-356 — §6 Editorial footer band
- **Follow-up**: ERD-357 — Admin UI for producer of the week
- **Follow-up**: ERD-358 — Admin UI for recipe of the week

## Status (2026-05-22)

**ERD-300 through ERD-350 are Done.** ERD-350 (per-user email history) was the last Backlog row of the original 50; shipped via the LogSentMail listener that writes `mail.sent` AuditEvent rows surfaced on the per-user admin page.

ERD-351 through ERD-358 are the dashboard-rebuild phase — currently **Backlog**. Pick them up in order; ERD-352…ERD-356 each depend on ERD-351 (the hero ships first and anchors the page).

Other open follow-up work lives in the post-redesign docs (`walkthrough-2-design-alignment.md`, `customer-ux-walkthrough-followups.md`, `admin-ux-walkthrough-followups.md`, `data-flow-walkthrough-followups.md`) — none of those rows live in this CSV.

## Marking tickets done

Per [`CLAUDE.md`](../CLAUDE.md): when a ticket from this file is completed, flip its `Status` column to `Done` in [`erdgut-redesign-plan.csv`](./erdgut-redesign-plan.csv) in the same commit as the implementation.
