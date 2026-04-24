# Onboarding payment redesign (ERD-345)

## Problem

Analytics showed a non-trivial drop between `/onboard/trial/delivery` → `/onboard/trial/payment` and again inside `/onboard/trial/payment` itself. The existing SumUp widget worked, but the UX around it left users uncertain at three specific moments:

1. **Pre-load.** The widget loaded cold with a generic "Loading payment widget..." text. Users hitting the page on slower connections (cellular in rural delivery zones) saw a blank card frame for 1–3 s and some bailed.
2. **Mid-submit.** Between the user clicking "Pay" and the browser redirecting after the 3-D Secure dance, the widget appeared frozen. No spinner, no "processing" state — just a filled form that stopped responding. Users clicked Pay twice, refreshed, or walked away.
3. **Context loss.** The payment step felt like a dead-end form. There was no visible path forward — "what happens after I enter my card?" — and no reassurance that this was the last step.

## Out of scope

- No re-ordering of wizard steps. The step sequence (account → address → preferences → commitment → delivery/schedule → payment) is already optimal and validated by the progress component.
- No changes to SumUp server-side flow, checkout creation, webhooks, or mandate setup. The fix is purely in the payment step UI.
- No A/B infrastructure. Manual smoke test against the before/after (see below).

## What changed (code)

### 1. `x-onboarding.payment-expectations`

New component above the SumUp widget showing three pill-style steps:

```
① Enter card details              ② Confirm with your bank        ③ Your trial box ships Monday
  30 seconds. Your card number       Approve via SMS, app, or       We compose it Thursday,
  never leaves SumUp.                bank popup (3-D Secure).       deliver it next week.
```

`aria-current="step"` on the first pill. Subscription variant shows "Your subscription is live / First delivery the following Monday."

**Why.** Gives users a mental model of the next ~60 seconds before they engage with the form, specifically naming the 3-D Secure step so the bank popup isn't a surprise.

### 2. `x-payment.security-badge`

Pill row below the widget:

```
🛡 Secured by SumUp     🔒 PCI DSS compliant     ✓ Your card number is never stored
```

**Why.** Addresses the exact phrase "is this safe?" without making the user hunt for it.

### 3. `x-payment.sumup-widget` — processing overlay

When the user clicks the widget's pay button (or when `onResponse` fires with `success`), an absolute-positioned white overlay covers the widget with a spinner + copy:

> **Processing your payment**
> Please do not close this window. Your bank may ask for a quick confirmation (SMS, app, or popup).

The overlay stays until the browser redirects. On error (`type === 'error' | 'fail'`) the state flips back to `ready` and the existing inline error message appears.

**Why.** Fills the 500ms–5s void between click and redirect. Explicitly tells the user what the bank popup is about to do.

### 4. Loading skeleton refinement

The skeleton now mimics a real card form (label + input + 2-column row + button) instead of generic bars, plus an "under 5 seconds" hint line. Cognitively matches what's loading.

### 5. Preconnect to gateway.sumup.com

`<link rel="preconnect" href="https://gateway.sumup.com" crossorigin>` + `<link rel="dns-prefetch" href="https://api.sumup.com">` in `layouts/onboarding.blade.php`. Starts the TLS handshake while earlier wizard steps are interactive, so the SDK `src=https://gateway.sumup.com/...` fetch feels near-instant when the user reaches the payment step.

## What didn't change

- Controllers, requests, services: untouched. The redesign is pure presentation.
- `SubscriptionOnboardingService`, `TrialOrderService`, webhook handling: no changes.
- SumUp widget JS callbacks (`onLoad`, `onResponse`): the payload contract is the same; the widget only gained internal Alpine state transitions.

## Manual smoke test (dropoff check)

Before declaring this "shipped," run this smoke test against staging in two browser profiles (clean + rural-mobile-throttled):

1. **Cold load.** Open staging in an Incognito window, throttle to Slow 3G in DevTools, walk through signup → delivery. On the payment page, confirm the expectations panel is visible above the skeleton within 200ms (the panel is pure Blade, no network). Confirm the skeleton shows the 5s hint.
2. **Happy path with 3-D Secure.** Use a SumUp test card that triggers 3-D Secure (check SumUp sandbox docs for the `4000 0025 0000 1001` equivalent). Click Pay. Confirm:
   - The processing overlay appears **before** the SumUp popup opens, not after.
   - The overlay stays visible through the 3-D Secure interaction.
   - After approval, the browser redirects to the completion page with no visible double-submit.
3. **Declined card.** Use a decline test card. Click Pay. Confirm:
   - Processing overlay appears briefly.
   - On decline, the overlay disappears and the inline error message shows under the widget (not a full page reload).
   - The user can re-enter details and retry without refreshing.
4. **Subscription variant.** Run the same 3 passes on `/onboard/subscription/payment`. Confirm the expectations panel says "Your subscription is live" not "Your trial box ships Monday."

### Dropoff signal

Watch these metrics in the week after ship:
- % of sessions that reach `/onboard/trial/payment` and complete to `/onboard/trial/complete/{order}`.
- % of sessions on the same page that trigger the `widget_error` console event twice (indicator of rage-click).

Target: +5 percentage points on completion rate. Measurement via manual funnel count in the ops spreadsheet is sufficient at current volume.

## Accessibility

- The processing overlay uses `role="status"` + `aria-live="assertive"` so screen readers announce the transition.
- The expectations panel uses `role="region"` + `aria-labelledby` and an `<ol>`/`<li>` structure so the 3 steps are navigable.
- `aria-current="step"` marks the active step.
- The security badge list uses `role="list"` with a descriptive `aria-label`.
- The SumUp iframe host retains `aria-busy` toggling.

## Rollback

All changes are presentation-only. To roll back, revert the commit — no DB, config, or data migration.
