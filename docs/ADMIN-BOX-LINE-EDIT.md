# Admin box line-item editing (ERD-192)

## Behaviour

Admins can add, update quantity, and remove `BoxItem` rows from the **admin box detail** page (`app.admin.boxes.show`). The same catalog rules as customers apply: only **published** and **available** products.

## Rules vs customers

By default (`ADMIN_BOX_LINE_EDIT_OVERRIDE=false`), mutations require:

- `BoxStatus::Editable`
- `modification_deadline_at` in the future

This mirrors customer `AddBoxItemRequest` / update / remove rules.

## Override (launch risk)

When `ADMIN_BOX_LINE_EDIT_OVERRIDE=true`, admins may edit lines even if the box is locked or past the deadline. Use only when operations explicitly accept the risk (customer-facing deadline no longer enforced for admin edits). Document the decision in release notes.

## Customer UI

When `CUSTOMER_BOX_EDITING_ENABLED=false`, customer-facing add / quantity / remove controls and related shop CTAs are hidden from `app.boxes.show`. Admins retain full edit capability on the admin box page.

## Technical notes

- New lines added by admin use `is_extra = true`, matching customer shop-style adds (`BoxController::addItem`).
- After each change, `BoxValueRecalculationService::recalculate` runs for the box.
