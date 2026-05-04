# Customer-side UX walkthrough — follow-up items

Items deferred during the browser walkthrough that produced PR `feature/onboarding-ux-walkthrough`. Each entry is sized roughly so it can be scoped into its own batch.

## Tone & translation

### Global formal → informal German pronoun pass — ✓ shipped

Verified 2026-05-03: zero customer-visible formal-pronoun instances remain in `lang/de.json` values. The two mid-sentence "Sie / Ihr" matches are 3rd-person feminine pronouns referring to female farmers (Anna, Elena bios) and are correct German.

The remaining lookup-key cleanup (9 email-template `__()` calls that used formal-German strings as the lookup key, even though the runtime value was already informal) was migrated to clean English keys at the same time so a future contributor "fixing" the formal lookup string can't accidentally break the i18n lookup.

### Brand voice glossary — ✓ shipped

`docs/brand-voice.md` now captures pronoun policy (informal `du/dein`, lowercase except sentence start), common verb-form replacements, tone notes (warm, editorial, factual; no corporate softeners), forbidden product words (`vegan`, `vegetarisch`), and punctuation/typography conventions (em dash, eyebrow uppercase, German number formatting).

## Architectural

### ~~🐛 `.env.example` ships with `APP_URL=http://localhost` (no port)~~ ✓ shipped (`fix/sumup-e2e-followups` 2026-05-04)

**Reproduced 2026-05-04 during real-SumUp E2E:** with `APP_URL=http://localhost` and `php artisan serve` on `:8000`, `route('app.payments.setup.complete', absolute: true)` generated `http://localhost/app/payments/setup/complete` — port 80, dead. SumUp redirected the customer's browser there after the widget; browser hit "Connection refused"; `PaymentSetupController.complete()` never ran; the SumUp checkout was PAID server-side but the local DB had token_len=0 + subscription stuck in `pending_payment`. Recovered manually by replaying `complete()` via tinker.

**Fix shape:** change `.env.example` line 6 to `APP_URL=http://localhost:8000` (matches `php artisan serve` default), OR add a comment `# Must include port if using artisan serve — e.g. http://localhost:8000`.

### ~~🐛 `sumup:reconcile-pending-payments` doesn't backfill `sumup_payment_id`~~ ✓ shipped (`fix/sumup-e2e-followups` 2026-05-04 — backfill in `SumUpWebhookHandler::handleCheckoutStatusChanged`, two regression tests)

**Reproduced 2026-05-04:** after a successful charge → reconcile cycle, Payment.status correctly flipped to `paid` but `sumup_payment_id` stayed empty. The transaction ID is available in the checkout's `transactions[0].id` field (verified via `getCheckout`). The webhook handler probably backfills it; reconcile should too, otherwise admin refund (which needs `sumup_payment_id` to call SumUp's refund API) silently degrades to local-only when the webhook didn't fire.

**Fix shape:** in `ReconcilePendingSumUpPayments::handle()` (or wherever the paid-status flip happens), also extract `data_get($checkout, 'transactions.0.id')` and persist to `Payment.sumup_payment_id`. One-line change + a feature test.

### ~~🔒 Onboarding step-gating~~ ✓ shipped (`fix/sumup-e2e-followups` 2026-05-04 — `RedirectToCorrectOnboardingStep` middleware applied to every step show-route, 6 forward/back/wrong-path tests)

**Reproduced 2026-05-04:** an authenticated customer can hit `http://.../onboard/subscription/payment` directly and it renders the SumUp checkout without checking that the prior wizard steps (account / address / preferences / commitment / schedule) are complete. The `OnboardingDraftManager::nextStep()` helper exists for exactly this — each step controller's `show()` should call it and redirect to the actual current step when the URL doesn't match.

**Why this matters:** the payment step calls `BoxPriceCalculator` which reads `box_size`, `preferences.eggs_dairy_add_on`, `plan` from the draft. With those unset, the price calc may degrade silently (or worse — charge for an inferred default the customer never picked).

**Fix shape:** add a `RedirectsToCorrectOnboardingStep` middleware (or a trait on the step controllers) that compares `$request->route()->getName()` against `$drafts->nextStep($draft, $user)` and redirects when they don't match. Cover with feature tests that hit each step URL with an empty/partial draft and assert the redirect.

**Why:** Direct URL access bypasses the conversion funnel — customers can land at payment with no understanding of what they're buying. Also potentially exposes the price calc to undefined-state inputs.

**How to apply:** When picking this up, audit all 7 onboarding step controllers (`Onboarding/AccountStepController`, `AddressStepController`, `PreferencesStepController`, `CommitmentStepController`, `Subscription/ScheduleStepController`, `Subscription/PaymentStepController`, `Trial/DeliveryStepController`, `Trial/PaymentStepController`) for the missing gate.

### ~~🔒 Email-verification gate doesn't block onboarding-route access~~ ✓ shipped (`fix/sumup-e2e-followups` 2026-05-04 — `RedirectIfOnboardingComplete` middleware: customers with active/pending Subscription get bounced to /app/dashboard or /auth/email/verify per their verification state)

**Reproduced 2026-05-04:** an authenticated-but-unverified user (just signed up, hasn't clicked the verification email) can navigate to any `/onboard/*` route and continue the wizard. The `verified` middleware is intentionally NOT applied to onboarding (since you can't verify before you have an account). But there's no gate to STOP an unverified user who is in some other state — e.g. one who just signed up and got bounced to `/auth/email/verify` shouldn't be able to backdoor into `/onboard/account` again.

**Decision needed:** should onboarding routes be gated by `verified` middleware ONCE the user has finished onboarding once (i.e. has a Subscription)? Or should they always be accessible to authenticated users (treating the wizard as idempotent)?

**Likely answer:** if a user has an active/pending Subscription, redirect /onboard/* away to dashboard (the wizard is single-shot, not for re-running). If they don't, allow access regardless of verification state.

**How to apply:** add a `RedirectIfOnboardingComplete` middleware on the `/onboard/*` group — if `auth()->user()->subscriptions()->whereIn('status', [...active, pending_payment])->exists()`, redirect to `/auth/email/verify` (if unverified) or `/app/dashboard` (if verified).

### Form-submission silent failures (~2h investigation, ~1-2h fix)

**Symptom:** in the preview browser session, a few onboarding form POSTs landed at `/` (home/coming-soon) with no error displayed and the user de-authed. Did not reproduce in a real Chrome session. Possibly preview-browser cookie quirk.

But the user-facing surface is shaped wrong regardless: a session expiry mid-form should produce an explicit "Sitzung abgelaufen — bitte erneut anmelden." flash + a clean redirect to `/auth/login` with `intended_url` retained, not a silent bounce.

Add a `Authenticate::redirectTo()` override that returns `/auth/login` always (never `/`), and a `419 Page Expired` handler with a German message + back-button affordance.

### Dashboard architecture pass (~6-8h)

The polish landed (state-aware empty states, trimmed quick actions, contextual link labels) but the dashboard still has structural overlap:

- "At a glance" 3-column panel + "Nächste Lieferungen" / "Box der aktuellen Woche" / "Almanach" 3-column row below say similar things at slightly different fidelities.
- For an **active subscriber with no current delivery yet** (the onboarding-just-finished state), the page is mostly empty cards.
- For a **steady-state subscriber** (deliveries scheduled), the top row is informative but the bottom row largely repeats it.

Worth a redesign session — pick one of:
- (a) Single chronological feed: next delivery first, then "this week's box", then almanach upcoming weeks
- (b) Two-column: left = "what's happening" (next delivery, box, alerts), right = "actions" (skip, pause, payment, address)
- (c) Status-conditional rendering: new subscribers see a "Welcome — your first box ships X" hero; steady-state sees a normal feed

Driven by what users actually need on this page each week. Worth instrumenting first (e.g. log click destinations) before redesigning blind.

## Smaller nice-to-haves

### ~~Cancel-modal default focus~~ ✓ shipped

`<x-secondary-button>` "Abo behalten" carries `data-default-focus`, and `components/modal.blade.php` now prioritises any `[data-default-focus]` child over the first focusable on open. Verified in PR #51 batch.

### ~~Schedule-step "PLANEN" label~~ ✓ already correct

`lang/de.json:663` already maps `"Schedule": "Zeitplan"`. The progress-bar's `uppercase` Tailwind class renders it as `ZEITPLAN` at runtime. Earlier "PLANEN" sighting was likely from a stale build / cached translation.

### Onboarding header brand (~1h, optional)

The onboarding pages render `<x-application-logo>` + the wordmark text "Erdgut" + the eyebrow "EIN PLATZ AM TISCH". Three brand elements stacked. Consider dropping the eyebrow on subsequent steps (only show on step 1) or dropping the wordmark text and letting the logo carry the brand.

### ~~Recurring-price heuristic for medium subscribers~~ ✓ obsolete

W2-16 collapsed first-vs-recurring entirely: every charge is now `BoxPriceCalculator::subscriptionPerDelivery($size, $eggsAddOn)`, so the payment-step price summary shows a single "Total per delivery" row plus an optional eggs-add-on adjustment. The "first box" / "following deliveries" split is gone.

### ~~Skip-card week range~~ ✓ already implemented

`resources/views/subscriptions/show.blade.php:246-248` renders the range as `4.–10. Mai` (same-month) / `27. Apr–3. Mai` (cross-month). Doc snapshot was outdated.

### Admin button styling consistency — ✓ shipped

Producer index Delete button migrated to `btn-danger-sm`, Edit link migrated to brand-ink underline-on-hover (small-polish-batch 2026-05-03). Other admin destructive actions verified as already on the brand button system.

## Not yet walked

- **Admin operations**: orders, deliveries, boxes, subscriptions, weekly catalog, payments, planner. This is a large surface — likely 1-2 sessions of its own.
- **Profile / settings**: `/account/profile` and friends. Quick — probably 1 session.
- **Public marketing pages**: `/`, `/about`, `/producers`, `/contact`. Currently in coming-soon mode.
- **Email rendering**: the 7 transactional Mailables we shipped in Batch D — render each in browser via `php artisan mailable:show` or staging preview.
- **Mobile viewport**: most of the walkthrough was at desktop width. The wizard and dashboard need a mobile pass.
- **Dark-mode / high-contrast**: not currently supported. Track if needed.
