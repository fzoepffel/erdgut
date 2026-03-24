# UX redesign baseline (ERD-168)

**Ticket:** ERD-168 — UX redesign baseline, competitor research, and route matrix  
**Phase:** 8 - UX & Design  
**Purpose:** Unblock ERD-169 through ERD-183 with shared principles, benchmarks, and a single source of truth mapping **named Laravel routes** to owning UX tickets.

---

## 1. Summary

Phase 8 optimizes the product for:

- **Subscriber clarity** — Users always know what happens next (next delivery, box deadline, payment status).
- **Weekly rhythm** — Surfaces match how people think: “this week”, “defaults vs one-off”, “skip / postpone / gift”.
- **Trust around payments** — SumUp setup, history, and errors feel safe and understandable (no jargon-heavy dead ends).

Implementation detail for shared components, empty states, and accessibility fixes is primarily **ERD-182** (UI kit) and **ERD-119** (a11y engineering backlog).

---

## 2. Competitive benchmarks

Study these (or close regional equivalents) for **patterns**, not pixel-perfect copying. For each, capture 1–2 notes on: landing story, account hub, delivery/skip/postpone language, product detail page, and payment reassurance.

| Reference | Region | What to study | Link |
|-----------|--------|---------------|------|
| **Oddbox** | UK | Veg-box narrative, impact messaging, account simplicity | <https://www.oddbox.co.uk/> |
| **Abel & Cole** | UK | Subscription management tone, delivery flexibility, organic trust | <https://www.abelandcole.co.uk/> |
| **Riverford** | UK | Farmer/provenance story, box customization mental model | <https://www.riverford.co.uk/> |
| **CrowdFarming** | EU | Producer-led PDPs, marketplace-style discovery | <https://www.crowdfarming.com/> |
| **HelloFresh DE** | DE | Stepped subscribe flow, account hub density (meal kit analogue) | <https://www.hellofresh.de/> |
| **Farmly** (or similar regional farm-box) | DE/EU | Local positioning and German-language UX expectations | _Replace with chosen local competitor when benchmarked._ |

**Outputs:** Screenshots or links stored in your design tool / Notion; ½ page “what we steal” vs “what we avoid” per brand optional but useful.

---

## 3. Design principles

- **Mobile-first** — Primary journeys work on a narrow viewport first; desktop is enhancement.
- **Hierarchy** — One primary action per view; secondary actions visually subdued.
- **Spacing scale** — Consistent vertical rhythm (e.g. Tailwind spacing tokens); avoid arbitrary gaps.
- **Typography** — Limited scale (display / title / body / caption); line length comfortable for reading on marketing pages.
- **CTAs** — Primary button for the next step in the journey; destructive actions (cancel, delete) require confirmation and plain-language consequences.
- **Cards vs lists** — “Next week” and delivery summaries read well as **cards**; long tabular history (payments, admin) may stay **tables** with mobile scroll or stacked rows.
- **States** — Every important surface specifies intent for **empty**, **loading**, and **error** (full implementation: **ERD-182**).
- **Accessibility** — Labels, focus order, contrast, and error association target WCAG 2.2 AA where feasible (**ERD-119**).

---

## 4. Route → ticket matrix

**Verified** against `php artisan route:list` in the repo (commit time: baseline creation). If routes change, update this table in the same PR as the route change.

### 4.1 Marketing (`public.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `public.home` | GET | `/` | ERD-169 |
| `public.about` | GET | `about` | ERD-170 |
| `public.producers` | GET | `producers` | ERD-171 |
| `public.contact` | GET | `contact` | ERD-172 |
| `public.contact.store` | POST | `contact` | ERD-172 |

### 4.2 Authentication (`auth/*`, `password.*`, `verification.*`)

| Route name | Method | URI (prefix `auth/` unless noted) | Owning ticket |
|------------|--------|-----------------------------------|----------------|
| `auth.register` | GET | `auth/register` | ERD-173 |
| `auth.register.store` | POST | `auth/register` | ERD-173 |
| `auth.login` | GET | `auth/login` | ERD-173 |
| `auth.login.store` | POST | `auth/login` | ERD-173 |
| `auth.logout` | POST | `auth/logout` | ERD-173 |
| `password.request` | GET | `auth/forgot-password` | ERD-173 |
| `password.email` | POST | `auth/forgot-password` | ERD-173 |
| `password.reset` | GET | `auth/reset-password/{token}` | ERD-173 |
| `password.store` | POST | `auth/reset-password` | ERD-173 |
| `verification.notice` | GET | `auth/verify-email` | ERD-173 |
| `verification.verify` | GET | `auth/verify-email/{id}/{hash}` | ERD-173 |
| `verification.send` | POST | `auth/email/verification-notification` | ERD-173 |
| `password.confirm` | GET | `auth/confirm-password` | ERD-173 |
| `auth.password.confirm.store` | POST | `auth/confirm-password` | ERD-173 |
| `password.update` | PUT | `auth/password` | ERD-173 |

### 4.3 Authenticated app hub

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.dashboard` | GET | `app/dashboard` | ERD-174 |

**App shell navigation (ERD-174):** Primary bar: Dashboard, Subscription, Deliveries, Shop extras (feature-flagged). Account menu (desktop dropdown + mobile block): Payments (`app.payments.history`), Delivery preferences, Profile, Log out. Admins see one **Admin** link in the primary bar (active on any `app.admin.*` route) and the same in the account menu; **Producers** and **Products** are reached from the admin dashboard, not from the global subscriber nav. Mobile menu toggle uses Alpine `mobileMenuOpen` consistently. Active nav states use emerald accents (aligned with ERD-173 guest/auth). QA checklist: [docs/UX-ERD-174-qa.md](UX-ERD-174-qa.md).

### 4.4 Subscriptions (`app.subscriptions.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.subscriptions.show` | GET | `app/subscriptions` | ERD-175 |
| `app.subscriptions.store` | POST | `app/subscriptions` | ERD-175 |
| `app.subscriptions.update` | PATCH | `app/subscriptions` | ERD-175 |
| `app.subscriptions.create` | GET | `app/subscriptions/create` | ERD-175 |
| `app.subscriptions.edit` | GET | `app/subscriptions/edit` | ERD-175 |
| `app.subscriptions.pause` | POST | `app/subscriptions/pause` | ERD-175 |
| `app.subscriptions.resume` | POST | `app/subscriptions/resume` | ERD-175 |
| `app.subscriptions.cancel` | POST | `app/subscriptions/cancel` | ERD-175 |

**Subscription UX (ERD-175):** `store` and `update` require `plan` (`weekly` | `biweekly`). Create flow shows step 1 of 2 with payment handoff on the overview. Overview uses sectioned layout, human-readable preferences (no JSON), emerald payment CTAs, German strings via `lang/de.json`. Pause and cancel use modals with clarified copy; resume uses a confirmation modal. Active subscribers who change `plan` on update get `next_charge_date` recalculated from today (`SubscriptionUpdateService` + `SubscriptionLifecycleService::computeNextChargeDate`). QA: [docs/UX-ERD-175-qa.md](UX-ERD-175-qa.md).

### 4.5 Deliveries (`app.deliveries.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.deliveries.index` | GET | `app/deliveries` | ERD-176 |
| `app.deliveries.preferences.edit` | GET | `app/deliveries/preferences` | ERD-176 |
| `app.deliveries.preferences.update` | PATCH | `app/deliveries/preferences` | ERD-176 |
| `app.deliveries.show` | GET | `app/deliveries/{delivery}` | ERD-176 |
| `app.deliveries.override.update` | PATCH | `app/deliveries/{delivery}/override` | ERD-176 |
| `app.deliveries.skip` | POST | `app/deliveries/{delivery}/skip` | ERD-176 |
| `app.deliveries.postpone` | POST | `app/deliveries/{delivery}/postpone` | ERD-176 |
| `app.deliveries.gift` | GET | `app/deliveries/{delivery}/gift` | ERD-176 |
| `app.deliveries.gift.store` | POST | `app/deliveries/{delivery}/gift` | ERD-176 |

**Deliveries UX (ERD-176):** `app.deliveries.index` is the single hub for upcoming weeks (timeline, one-time overrides, skip / postpone / gift). `app.deliveries.show` **redirects** to the index with a `focus` query parameter on the base delivery row—there is no separate detail Blade view. QA: [docs/UX-ERD-176-qa.md](UX-ERD-176-qa.md).

### 4.6 Boxes (`app.boxes.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.boxes.current` | GET | `app/boxes/current` | ERD-177 |
| `app.boxes.show` | GET | `app/boxes/{box}` | ERD-177 |
| `app.boxes.items.add` | POST | `app/boxes/{box}/items` | ERD-177 |
| `app.boxes.items.updateQuantity` | PATCH | `app/boxes/{box}/items/{item}` | ERD-177 |
| `app.boxes.items.remove` | DELETE | `app/boxes/{box}/items/{item}` | ERD-177 |

**Box composition UX (ERD-177):** `app.boxes.show` is the weekly composition hub: editable vs read-only state, deadline + coarse countdown, budget panel (included value and remainder aligned with shop), touch-friendly quantity controls in edit mode, optional mobile sticky estimated-value bar, and `__()` / `de.json` strings. Item mutations remain JSON APIs from the same page or shop. QA: [docs/UX-ERD-177-qa.md](UX-ERD-177-qa.md).

### 4.7 Shop (`app.shop.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.shop.index` | GET | `app/shop` | ERD-178 |
| `app.shop.show` | GET | `app/shop/{product}` | ERD-178 |

**Shop UX (ERD-178):** `app.shop.index` includes an intro strip, GET toolbar (search + sort with validation), category/producer chips that preserve `q` and `sort`, collapsible filter block on small viewports, and i18n empty states with clear-actions. `app.shop.show` surfaces optional `metadata.characteristics` (list) and `metadata.dietary_notes` (string), a clear no–editable-box panel with CTAs to the current box and dashboard, and accessible add-to-box error feedback. Catalog loading remains full-page GET navigation; add-to-box buttons retain loading/disabled behaviour. QA: [docs/UX-ERD-178-qa.md](UX-ERD-178-qa.md).

### 4.8 Payments (`app.payments.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.payments.history` | GET | `app/payments/history` | ERD-179 |
| `app.payments.setup` | GET | `app/payments/setup` | ERD-179 |
| `app.payments.setup.complete` | GET | `app/payments/setup/complete` | ERD-179 |
| `app.payments.update` | GET | `app/payments/update` | ERD-179 |

**Payments UX (ERD-179):** `app.payments.history` is the primary hub: mandate status, next charge, trust copy, CTAs (setup/update/subscription/dashboard), shared flash partial for payment session keys, and receipt-style charge history with safe failure hints. `app.payments.setup` / `update` share a sectioned layout, SumUp widget locale from `config('app.locale')`, allowlisted widget error mapping, and accessible error output. `app.payments.setup.complete` redirects success and most failures to payment history with existing status keys. Billing subscription resolution uses `Subscription::billingSubscriptionForUser()` shared with setup. QA: [docs/UX-ERD-179-qa.md](UX-ERD-179-qa.md).

### 4.9 Account (`account.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `account.profile.edit` | GET | `account/profile` | ERD-180 |
| `account.profile.update` | PATCH | `account/profile` | ERD-180 |
| `account.profile.destroy` | DELETE | `account/profile` | ERD-180 |
| `account.users.show` | GET | `account/users/{user}` | ERD-180 |

### 4.10 Admin (`app.admin.*`)

| Route name | Method | URI | Owning ticket |
|------------|--------|-----|----------------|
| `app.admin.dashboard` | GET | `app/admin` | ERD-181 |
| `app.admin.producers.index` | GET | `app/admin/producers` | ERD-181 |
| `app.admin.producers.create` | GET | `app/admin/producers/create` | ERD-181 |
| `app.admin.producers.store` | POST | `app/admin/producers` | ERD-181 |
| `app.admin.producers.edit` | GET | `app/admin/producers/{producer}/edit` | ERD-181 |
| `app.admin.producers.update` | PUT/PATCH | `app/admin/producers/{producer}` | ERD-181 |
| `app.admin.producers.destroy` | DELETE | `app/admin/producers/{producer}` | ERD-181 |
| `app.admin.products.index` | GET | `app/admin/products` | ERD-181 |
| `app.admin.products.create` | GET | `app/admin/products/create` | ERD-181 |
| `app.admin.products.store` | POST | `app/admin/products` | ERD-181 |
| `app.admin.products.edit` | GET | `app/admin/products/{product}/edit` | ERD-181 |
| `app.admin.products.update` | PUT/PATCH | `app/admin/products/{product}` | ERD-181 |
| `app.admin.products.destroy` | DELETE | `app/admin/products/{product}` | ERD-181 |

### 4.11 Cross-cutting (no dedicated route)

| Scope | Owning ticket |
|-------|----------------|
| Shared UI kit, tokens, empty/loading/error patterns | ERD-182 |

### 4.12 Ops / non-customer UX (see ERD-183)

| Route name | Method | URI | Notes |
|------------|--------|-----|--------|
| `public.health` | GET | `health` | Diagnostics; no marketing UX |
| `public.cron.run` | GET | `cron/run` | Secured ops trigger; no customer UX |
| `sumup.webhook` | POST | `api/sumup/webhook` | Machine endpoint; no UI |

### 4.13 Framework / infrastructure (out of Phase 8 visual scope)

| Route name | Purpose |
|------------|---------|
| `storage.local` | Static asset delivery |
| Laravel `up` | Framework health endpoint (unnamed in default listing) |

---

## 5. Proposed P0 implementation order

Order aligns with **onboarding → conversion → weekly loop → extras → acquisition polish → account/admin/kit**.

| Priority | Tickets | Rationale |
|----------|---------|-----------|
| 1 | **ERD-173**, **ERD-174** | Auth shell + dashboard: orientation and navigation IA |
| 2 | **ERD-175**, **ERD-179** | Subscription + payments: convert and establish trust |
| 3 | **ERD-176**, **ERD-177** | Deliveries + box: core weekly subscriber loop |
| 4 | **ERD-178**, **ERD-169–ERD-172** | Shop extras + marketing: acquisition and upsell |
| 5 | **ERD-180**, **ERD-181**, **ERD-182** | Account, admin, UI kit: parallel or after P0 surfaces |

---

## 6. Product sign-off (P0 page order)

**Proposed order** is section 5 above.

| Field | Status |
|-------|--------|
| **Approved by** | _Pending — record name when product owner agrees_ |
| **Date** | _Pending_ |
| **Notes** | _Optional: e.g. deprioritize marketing if launch is subscriber-only beta_ |

When approved, update this table and optionally mark **ERD-168** Done in `erdgut-mvp-tickets.csv`.

---

## 7. Child tickets (quick index)

| Ticket | Title (short) |
|--------|----------------|
| ERD-169 | Marketing home |
| ERD-170 | About |
| ERD-171 | Producers |
| ERD-172 | Contact |
| ERD-173 | Auth screens |
| ERD-174 | App shell + dashboard |
| ERD-175 | Subscriptions |
| ERD-176 | Deliveries |
| ERD-177 | Weekly box |
| ERD-178 | Shop |
| ERD-179 | Payments |
| ERD-180 | Account / profile |
| ERD-181 | Admin back-office |
| ERD-182 | UI kit + cross-cutting states |
| ERD-183 | Ops routes scope note |
