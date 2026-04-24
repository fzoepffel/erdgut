# Transactional Emails Matrix

_Index of every customer-facing transactional email Erdgut queues, where it
originates, what it triggers on, and how the admin can re-dispatch it._

This is the single source of truth for ERD-339 (bodies), ERD-341 (this matrix),
and ERD-332 (admin resend). Keep it in lockstep when new Mailables are added.

## Conventions

- All Mailables extend Laravel's `Illuminate\Mail\Mailable` and are **queued**
  via `Mail::to(...)->queue(...)` — never sent synchronously. This keeps the
  request path free of SMTP latency.
- Every template has a multipart payload: HTML (`resources/views/emails/*.blade.php`)
  plus plain text (`*-text.blade.php`).
- Subject + body copy lives under `lang/{en,de,fr}.json` keyed `emails.<slug>.*`.
  The three locales are kept in parity by `TranslationParityTest`.
- Recipient is always `$entity->user->email`. Dispatch short-circuits silently
  when the user has no email on file.
- Each Mailable accepts a single model (or, for `SubscriptionSkipConfirmationMail`,
  a model plus a `list<string>` of ISO Monday dates).

## Matrix

| Template key | Mailable | Recipient | Trigger (code path) | Tests |
|---|---|---|---|---|
| `subscription.activated` | `SubscriptionActivatedMail` | subscription user | `SubscriptionOnboardingService::finalizeSetup` — right after `$subscription->activate()` | `AdminEmailResendTest::test_admin_can_resend_subscription_activated` |
| `subscription.skip_confirmation` | `SubscriptionSkipConfirmationMail` | subscription user | `SubscriptionSkipService::syncUserSkipWeeks` — after DB sync + audit log | `TransactionalEmailDispatchTest::test_skip_weeks_sync_queues_confirmation_mail`, `AdminEmailResendTest::test_admin_can_resend_skip_confirmation_with_current_user_weeks` |
| `subscription.cancellation` | `SubscriptionCancellationMail` | subscription user | `SubscriptionLifecycleService::cancelNow` — after cancellation + audit log | `TransactionalEmailDispatchTest::test_cancel_now_queues_cancellation_mail` |
| `subscription.pause_exhausted` | `SubscriptionPauseExhaustedMail` | subscription user | `PauseExhaustedSubscriptions` console command — per paused subscription in the exhausted loop | `TransactionalEmailDispatchTest::test_pause_exhausted_command_queues_pause_mail` |
| `box.composed` | `BoxComposedMail` | box user | `BoxCompositionService::compose` — after items are inserted and estimated value recalculated | `TransactionalEmailDispatchTest::test_box_composition_queues_box_composed_mail` |
| `delivery.in_transit` | `DeliveryInTransitMail` | delivery user | `AdminDeliveryController::update` / `AdminDeliveryController::updateStatus` when status flips to `in_transit` | `TransactionalEmailDispatchTest::test_admin_status_update_queues_in_transit_mail` |
| `delivery.delivered` | `DeliveredMail` | delivery user | `AdminDeliveryController::update` / `AdminDeliveryController::updateStatus` when status flips to `delivered` | `TransactionalEmailDispatchTest::test_admin_status_update_queues_delivered_mail` |
| `order.trial_confirmation` | `TrialOrderConfirmationMail` | order user | `TrialOrderService::confirmPaidFromProvider` (return-url reconcile) and payment webhook — on the Paid transition | `TrialOrderServiceTest`, `AdminEmailResendTest` |
| `order.trial_delivery_scheduled` | `TrialDeliveryScheduledMail` | order user | `AdminOrderController::schedule` — when admin sets `scheduled_for_date` | `AdminOrderControllerTest` |
| `order.trial_convert` | `TrialConvertToSubscriptionMail` | order user | `SendTrialConversionReminders` console command | existing conversion-reminder tests |

## Admin resend (ERD-332)

- Route: `POST /admin/emails/resend` (`app.admin.emails.resend`), throttled at
  30 req/minute.
- Controller: `AdminEmailResendController::store` resolves the template → Mailable +
  recipient via `match()`. Unknown entity IDs or missing recipient short-circuit
  to a `admin-email-resend-not-found` flash. Successful resends queue the mail
  and write an `AuditEvent` (`action = email_resend`, `metadata = { template,
  recipient }`).
- Request: `AdminEmailResendRequest` validates `template` against the
  `TEMPLATES` constant and requires one of `subscription_id`, `box_id`,
  `delivery_id`, `order_id` to exist in the matching table.
- UI: `<x-admin.email-resend context="…" :entity-id="…">` is wired on:
  - `admin/operations/deliveries/edit.blade.php` (context `delivery`)
  - `admin/operations/boxes/show.blade.php` (contexts `box` + `subscription`)
  - `admin/operations/payments/show.blade.php` (context `subscription` or `order`
    depending on which the payment is linked to)
  - `admin/orders/show.blade.php` (context `order`)
- Every resend is traceable via the audit log for the target entity.

## Adding a new transactional email

1. Create the `App\Mail\<Name>Mail` class (extend `Mailable`, use `Queueable`,
   accept model(s) via constructor property promotion).
2. Add HTML + plain text views under `resources/views/emails/` extending
   `emails.layout`.
3. Add `emails.<slug>.subject/eyebrow/heading/intro/cta/...` keys to all three
   `lang/*.json` files (parity test will fail otherwise).
4. Wire dispatch from the service / controller / command that owns the state
   transition using `Mail::to($email)->queue(new <Mailable>(...))`.
5. Add a dispatch test (see `TransactionalEmailDispatchTest` for the pattern).
6. Register the template in `AdminEmailResendRequest::TEMPLATES` and extend the
   `match()` in `AdminEmailResendController::resolve`.
7. Add the template label under `admin.operations.emails.resend.<slug>` in all
   three locales and surface the component on the relevant admin detail page.
8. Add a row to the matrix above.
