# Payments and billing UX (ERD-179)

**Ticket:** ERD-179 — Payment setup, history, and update UX  
**Routes:** `app.payments.history`, `app.payments.setup`, `app.payments.setup.complete`, `app.payments.update`  
**Purpose:** Manual QA after payments hub, mandate status strip, SumUp widget framing, redirects after `complete`, and receipt-style charge history.

---

## Preconditions

- Authenticated subscriber with and without `sumup_token` on the billing subscription (active-first resolution matches checkout flows).
- SumUp sandbox or test keys so setup/update widgets load.
- At least one `payments` row (paid and failed) for ledger layout — optional for empty-state check.

---

## Payment overview (`app/payments/history`)

1. **Mandate strip** — States clearly whether a payment method is on file; no card number or token shown. Next charge date matches subscription semantics (“Set after first charge” when null).
2. **Trust copy** — Short note that entry is on SumUp and full PAN is not stored on Erdgut servers.
3. **CTAs** — Set up / Update / Subscription overview / Dashboard appear according to subscription state (cancelled subscribers: no setup/update if spec says so — cancelled + no token shows “no method” without setup CTA).
4. **Flashes** — After `setup.complete`, success and failure messages appear on this page (`payment-setup-success`, `payment-updated`, `payment-setup-failed`, `payment-setup-unavailable`).
5. **Charge history** — Dates `d.m.Y`; mobile stacked receipts; desktop table with caption; description is user-safe (plan + subscription charge label).
6. **Failed rows** — Human `failure_reason` text shown when safe; technical strings show generic “Payment failed” only.
7. **German** — With `APP_LOCALE=de`, spot-check new strings and nav label “Zahlungen”.

---

## Setup and update (`app/payments/setup`, `app/payments/update`)

1. **Sections** — Step 1 trust strip; “Payment details” region with `aria-labelledby`.
2. **Widget** — Loads with locale from `app.locale` (`de` → `de-DE`, default → `en-GB`).
3. **Errors** — Decline and errors surface `role="alert"` and `aria-live="polite"`; no raw SumUp jargon for unknown codes.
4. **Navigation** — Back to payment overview + subscription overview links work.

---

## Complete redirect (`app/payments/setup/complete`)

1. Successful setup and update redirect to **payment history** with the correct flash key.
2. Mismatch / API failure redirects to history with `payment-setup-failed` where implemented.

---

## Subscription overview

1. Payment-related session statuses still render when the user lands on subscription (e.g. `payment-already-setup` after visiting setup with token present).

---

## Regression

- Throttle middleware still on setup routes.
- No payment tokens or SumUp secrets in HTML or test assertions.

---

## Automated coverage

`tests/Feature/Payment/PaymentSetupControllerTest.php` and `tests/Feature/Payment/PaymentHistoryControllerTest.php` cover redirects, mandate copy, and ledger rows.
