# Billing and manual launch (ERD-189)

This document describes how **scheduled recurring charges** and **future admin-initiated charges** coexist, how **fulfillment weeks** are recorded, and how **double charges** are avoided.

## Charge entry point

All subscription recurring SumUp checkouts (scheduled daily job, payment retry job, and future admin “Run charge”) must go through **`App\Services\Billing\SubscriptionRecurringChargeService`**. That keeps `payments` rows, checkout references, and SumUp calls consistent.

## Payment metadata

| Field | Purpose |
|-------|---------|
| `charge_initiator` | `scheduled` (cron), `retry` (payments:retry), or `admin_manual` (future admin UI). |
| `initiated_by_user_id` | Set for `admin_manual` when an operator triggers a charge. |
| `fulfillment_week_start` | Monday of the billed box week (aligned with `boxes.week_start_date`). Set for **scheduled** charges; optional/null for retries and some manual flows. |
| `box_id` | Optional link to the `boxes` row for that user/week when one exists. |

### Scheduled fulfillment week rule

For **`subscriptions:charge`**, `fulfillment_week_start` is the **Monday of the ISO week** that contains the subscription’s `next_charge_date`. If `next_charge_date` is null, the **Monday of the week containing “today”** (command run date) is used.

## `next_charge_date` and the webhook

On successful payment, **`SumUpWebhookHandler`** marks the payment **paid** and advances **`subscription.next_charge_date`** for active subscriptions (unchanged). That remains the primary guardrail so the scheduler does not immediately re-select the same subscription.

## Double-charge avoidance (same fulfillment week)

Additionally, **`subscriptions:charge`** skips a subscription if there is already a **paid** payment for the same **`subscription_id`** and **`fulfillment_week_start`** as computed for that run. This covers edge cases where `next_charge_date` has not yet advanced but the week was already billed (e.g. manual operations, timing).

Legacy paid rows may have **`fulfillment_week_start` = null**; they do not participate in this week-based skip until new charges write the field.

## Customer box editing

**`CUSTOMER_BOX_EDITING_ENABLED`** (default `true`) controls whether customers can add/remove/change box line items in the app. Set to `false` for preset-only launch boxes. The admin box detail page can still mutate lines; see **[docs/ADMIN-BOX-LINE-EDIT.md](ADMIN-BOX-LINE-EDIT.md)** for **`ADMIN_BOX_LINE_EDIT_OVERRIDE`** (editing locked or past-deadline boxes).

## Admin billing coverage and manual charge

The **Billing coverage** admin screen lists active subscriptions against planner horizon weeks (paid / pending / scheduled auto). **Run charge** uses `SubscriptionRecurringChargeService` with `charge_initiator = admin_manual` and `initiated_by_user_id`. It must not double-charge a week that already has a **paid** payment for that fulfillment Monday.

## Admin box planner horizon

**`ADMIN_BOX_PLANNER_WEEKS_AHEAD`** (default `4`, max `8`) is reserved for the admin weekly box planner (ERD-190+); see `config('admin.box_planner_weeks_ahead')`.
