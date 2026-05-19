# Inventory rebuild — end-to-end testing plan

This document walks through every test for the daily-inventory rebuild
(Phases 1–5). Run it on staging first; once green, repeat on prod
after the corresponding merge.

Each section ends with a clear pass/fail. If anything fails, stop and
report — don't continue further down.

---

## 0. Pre-deploy sanity (do this before merging Phase 1)

| Check | How | Expected |
|---|---|---|
| Production has a fresh DB backup | IONOS panel or `mysqldump` via SSH | A dated `.sql.gz` from within the last hour |
| All env vars present | `cat .env \| grep -E 'SPARGEL_PRODUCT_ID_|DELIVERY_DEFAULT'` | `SPARGEL_PRODUCT_ID_WHITE`, `SPARGEL_PRODUCT_ID_GREEN`, `DELIVERY_DEFAULT_ZONE` all set |
| Today's spargel orders backed up | `php artisan tinker --execute="echo App\Models\SpargelOrder::count();"` | Note the number. Re-run after each migration deploy — should never decrease |
| Existing weekly stock rows visible | `php artisan tinker --execute="echo DB::table('product_week_stocks')->count();"` | Some positive number (or 0 if nothing was set up) |

> **Stop and check** if `SPARGEL_PRODUCT_ID_*` are missing — Phase 4
> won't work without them.

---

## 1. After deploying Phase 1 (schema + data migration)

**Manual deploy step:** `php artisan migrate --force` (4 migrations).

### 1.1 Schema present

```bash
php artisan tinker --execute="
echo 'inflows: ' . Schema::hasTable('product_stock_inflows') . PHP_EOL;
echo 'schedules: ' . Schema::hasTable('product_stock_inflow_schedules') . PHP_EOL;
echo 'product col: ' . Schema::hasColumn('products', 'default_freshness_window_days') . PHP_EOL;
"
```

Expect three `1`s.

### 1.2 Data migration count parity

```bash
php artisan tinker --execute="
\$ws = DB::table('product_week_stocks')->count();
\$inf = DB::table('product_stock_inflows')->count();
\$wsch = DB::table('product_week_stock_schedules')->count();
\$isch = DB::table('product_stock_inflow_schedules')->count();
echo 'weekly stocks: ' . \$ws . ', daily inflows: ' . \$inf . PHP_EOL;
echo 'weekly schedules: ' . \$wsch . ', inflow schedules: ' . \$isch . PHP_EOL;
"
```

Expect `weekly stocks == daily inflows` and `weekly schedules ==
inflow schedules`. **Stop if they diverge** — the data migration
missed rows.

### 1.3 Idempotency

Run `php artisan migrate --force` a second time. It should report zero
migrations to run (since they all completed in step 1.1). If you
manually invoke the data migration class twice, the second invocation
should be a no-op (re-runs detect the `[migrated:wsid=…]` marker on
notes).

### 1.4 Old paths still work

- Visit `/app/admin/products/weekly` — should still render the old
  Wochenkatalog (Phase 1 doesn't change UI yet).
- Place a test spargel order via `/spargel` — should succeed exactly
  as before (Phase 1 doesn't change the customer side yet).

**Pass criteria:** counts match, old UI works.

---

## 2. After deploying Phase 2 (service layer + cron rename)

**Manual deploy step:** no migration; `php artisan config:clear` is
handled by the deploy workflow.

### 2.1 New cron entry registered

```bash
php artisan schedule:list | grep -E 'materialize|stock:'
```

Expect to see `stock:materialize-inflow-schedules` running on Mondays
03:00. The old `stock:materialize-schedules` is gone (or appears with
a "Forwards to..." deprecation note if your cron still calls it).

### 2.2 Materializer is idempotent

```bash
php artisan stock:materialize-inflow-schedules
php artisan stock:materialize-inflow-schedules
```

First run may write rows (extending the 30-week horizon). Second run
should write `0`.

### 2.3 Old command shim still dispatches

```bash
php artisan stock:materialize-schedules
```

Should print "deprecated; forwarding to ..." and behave the same.

### 2.4 Service-level sanity (tinker)

```bash
php artisan tinker --execute="
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\InventoryService;
use Illuminate\Support\Carbon;

\$p = Product::first();
if (\$p === null) { echo 'no products yet'; exit; }
echo 'available today: ' . app(InventoryService::class)->availableOnDate(\$p, Carbon::today()) . PHP_EOL;
echo 'committed today: ' . app(InventoryService::class)->committedOnDate(\$p, Carbon::today()) . PHP_EOL;
"
```

Numbers can be anything; the point is they return without error.

**Pass criteria:** cron renamed, materializer idempotent, service
returns numbers without throwing.

---

## 3. After deploying Phase 3 (admin Inventar UI)

**Manual deploy step:** none.

### 3.1 Navigation rename

Log in as admin → sidebar shows **"Inventar"** (was "Wochen-Katalog").

### 3.2 Legacy redirect

Visit `/app/admin/products/weekly` directly → browser should redirect
to `/app/admin/inventory`. Visit `/app/admin/products/weekly/2026-06-08`
→ redirects to `/app/admin/inventory?from=2026-06-08`.

### 3.3 Inventar page loads with 4 sections

Open `/app/admin/inventory`:
- Header "Inventar"
- Window navigator with date picker + Previous/Today/Next buttons
- Section A "Eingänge"
- Section B "Verfügbarkeit pro Tag"
- Section C "Wiederkehrende Eingänge"
- Section D "Manueller Eingang"

### 3.4 Manual inflow round-trip

In Section D:
- Pick a product (e.g. spargel weiß).
- Today's date.
- Quantity `2` (in the product's display unit, e.g. kg).
- Freshness `1`.
- Submit.

Verify:
- Green flash "Eingang erfasst".
- Section A now lists today's inflow with the new row.
- Section B's "today" column for that product shows `2`.

### 3.5 Delete inflow

Click "Löschen" on the row you just created. Confirm. Row disappears;
Section B's cell goes back to `—` or the previous value.

### 3.6 Recurring schedule creation

In Section C, find a product and expand:
- Click "Neue Regel anlegen".
- Weekday `Mittwoch`.
- Quantity `10` (display unit).
- Freshness `1`.
- Starts on next Wednesday.
- Submit.

Verify:
- Green flash "Wiederkehrende Lieferung angelegt".
- The schedule appears in that product's Section C panel.
- Section A now shows multiple Wednesdays (one per upcoming Wed for the
  next ~26 weeks).
- Section B's Wednesday columns now light up with `10`.

### 3.7 Schedule cancel preserves manual override

For a different product, set up a Monday schedule (qty `5`). After
materialization writes a few rows, manually edit one row in the
database to qty `7` (this simulates an admin one-off override —
normally a future UI affordance). In tinker:

```bash
php artisan tinker --execute="
use App\Models\ProductStockInflow;
\$inflow = ProductStockInflow::query()
    ->whereNotNull('source_schedule_id')
    ->orderBy('id', 'desc')
    ->first();
echo 'override candidate id=' . \$inflow->id . PHP_EOL;
\$inflow->update(['quantity' => 7]);
"
```

Now cancel the schedule from a date *before* the overridden row. The
overridden row should survive; same-quantity rows after that date
should be deleted.

### 3.8 Old Wochenkatalog POST returns 410

```bash
curl -X POST https://staging.erd-gut.de/app/admin/products/weekly/2026-06-08 \
  -H "Cookie: ..." -i
```

Expect HTTP 410.

**Pass criteria:** nav renamed, redirect works, all 4 sections
functional, schedule lifecycle preserved on cancel.

---

## 4. After deploying Phase 4 (spargel customer migration)

**Manual deploy step:** none. Verify `SPARGEL_PRODUCT_ID_WHITE` and
`SPARGEL_PRODUCT_ID_GREEN` are set.

### 4.1 Set spargel freshness to 1 day

For each of the two spargel products on `/app/admin/inventory`,
override `default_freshness_window_days` to `1` (via product edit page
or tinker). Migrated schedules keep their old 7-day freshness from
Phase 1 — to actually exercise the "1 day only" behaviour you must
explicitly set this.

```bash
php artisan tinker --execute="
App\Models\Product::whereIn('id', [
    config('spargel.white_product_id'),
    config('spargel.green_product_id'),
])->update(['default_freshness_window_days' => 1]);
"
```

### 4.2 Customer sees only days with active inflows

Open `/spargel` (no auth). The date picker should list **only** dates
where both spargel products have inflows whose freshness window covers
that date.

- If your only schedules are Monday-anchored with freshness=1, you'll
  see Mondays only.
- Add a Wednesday schedule for both spargel products → reload `/spargel`
  → Wednesdays now appear too.
- No dates should be hardcoded — the picker is purely a function of
  inflows + freshness.

### 4.3 Per-date cap enforces

Pick a date where remaining_white_grams = 5000 (5 kg). Order 4 kg → 
accepted. Order another 2 kg for the **same date** → rejected with
the "Für diese Woche sind nur noch X g..." error message.

### 4.4 Same-day exclusion still holds

If today is in the same weekday family as one of your schedules,
**today should not appear** in the picker. The picker shows
`[today+1, today + 8 weeks]`.

### 4.5 Freshness window opens future days

Set spargel-white freshness to `3`. Add a Wednesday schedule.
Reload `/spargel` — Wed, Thu, AND Fri should now appear (3-day
window). Available green grams on Thu/Fri equals the Wed inflow
minus any committed orders for those days.

### 4.6 Cancel a schedule and confirm picker updates

Cancel the Wednesday schedule from next Wed. Reload `/spargel` — next
Wednesday's option should vanish (assuming no manual inflow there).
Wednesdays beyond the cancel date stay gone too.

### 4.7 Existing spargel order flow still works end-to-end

- Place a complete order with both varieties, delivery, payment via
  SumUp on staging.
- Confirmation email arrives.
- Order row visible in `/app/spargel-admin`.

**Pass criteria:** date picker is data-driven (no hardcoded Mondays),
per-date cap enforced, freshness extends the window, full
order-to-paid-to-fulfilled flow works.

---

## 5. After deploying Phase 5 (box-setup migration + legacy deletes)

**Manual deploy step:** none.

### 5.1 Box setup loads against delivery date, not Monday

Open `/app/admin/boxes/setup/<next-monday>`. The "available products"
panel should list:
- Every product with an active inflow on the **Wednesday** of that
  week (since `delivery.allowed_weekdays = [3]`).
- Products with Mon-received inflows + freshness ≥ 3 days should
  appear (their window covers Wed).
- Products with only Friday-received inflows should NOT appear.

### 5.2 Save a box, verify outflow counted

Save a box for the upcoming week with a few line items. Re-open
`/app/admin/inventory`. The matrix's Wednesday column for the chosen
products should now show `availableOnDate − boxQuantity` (smaller).

### 5.3 Subscription with different delivery_weekday (unit-tested)

Currently the zone only allows Wed, so this is an integration-test
case rather than a manual one. Verify via tinker:

```bash
php artisan tinker --execute="
use App\Support\BoxDeliveryDateResolver;
use App\Models\Box;
\$box = Box::query()->latest()->first();
if (\$box === null) { echo 'no boxes'; exit; }
echo 'box id=' . \$box->id . PHP_EOL;
echo 'week_start=' . \$box->week_start_date . PHP_EOL;
echo 'resolved delivery=' . BoxDeliveryDateResolver::deliveryDate(\$box)->toDateString() . PHP_EOL;
"
```

The resolved date should be the Wednesday of the box's week.

### 5.4 Old routes 404

`/app/admin/products/weekly` should 404 now (the redirect was removed
in Phase 5). Same for `products.schedules.*` URLs.

### 5.5 Legacy command shim removed

`php artisan stock:materialize-schedules` should error with "Command
not found". Only `stock:materialize-inflow-schedules` works.

### 5.6 Test suite parity

Pull the `staging` branch locally and run the full suite:

```bash
php artisan test
```

Expect ~1080 tests pass, 0 failures, 0 skipped (the legacy-skipped
tests are deleted in Phase 5).

**Pass criteria:** box-setup uses daily inventory, old routes/commands
gone, full suite green with no skipped tests.

---

## 6. End-to-end golden path (run on staging once Phases 1–5 are all live)

This simulates a realistic week-of-spargel admin + customer flow.

| Step | Actor | Action | Verify |
|---|---|---|---|
| 1 | Admin | `/app/admin/inventory` → Section C: Spargel weiß, weekday Mittwoch, qty 10 kg, freshness 1, indefinite | New schedule appears. Section A lists ~26 Wednesdays. |
| 2 | Admin | Same, but Montag for spargel weiß, qty 10 kg, freshness 1 | New schedule appears alongside Wednesday. |
| 3 | Admin | Section D: today's harvest, Spargel weiß, qty 2 kg, freshness 1 | Today shows up in Section A with the manual flag. |
| 4 | Customer | `/spargel` | Date picker shows: today (if today is Mon or Wed), next Monday, next Wednesday, then alternating for 8 weeks. |
| 5 | Customer | Order 5 kg weiß for next Wednesday | Accepted; thank-you page. |
| 6 | Customer | Order another 6 kg weiß for the same next Wednesday | Rejected with per-date cap error. |
| 7 | Admin | Refresh `/app/admin/inventory` | Section B's next-Wed column for Spargel weiß shows `10 − 5 = 5` kg available. |
| 8 | Admin | Section C: cancel the Wednesday schedule starting in 4 weeks | Wednesdays in week 1–3 stay materialized. Week 4+ vanish from Section A and Section B. |
| 9 | Customer | Reload `/spargel` | Wednesdays in weeks 1–3 still appear; week 4+ Wednesdays gone. |
| 10 | Admin | `/app/admin/boxes/setup/<next-monday>` | Spargel weiß listed in the available-products panel. |

If any step fails: stop, capture the error, report.

---

## 7. Rollback procedure (in case something goes catastrophically wrong)

Per the plan, legacy tables (`product_week_stocks`,
`product_week_stock_schedules`) stay around until Phase 6. To roll
back to pre-Phase-1 behaviour:

1. Revert the relevant commits on `main`.
2. Re-deploy.
3. `php artisan migrate:rollback --step=4` on the server (rolls back
   the 4 Phase-1 migrations; the new tables disappear and the old
   tables still have their data).

If we're already past Phase 6 (legacy tables dropped):
- Restore the database from the backup taken in Section 0.
- Then revert and redeploy.

This is why Section 0 demands a fresh backup before Phase 1.

---

## Notes for tester

- **Open two browser windows** — one logged in as admin, one
  incognito for `/spargel`. Lets you trigger admin changes and
  immediately see the customer effect.
- **Watch the laravel.log** during the customer flow:
  `tail -f storage/logs/laravel-*.log` — any 500s or unexpected
  warnings surface immediately.
- **The matrix in Section B is the single best diagnostic.** If
  something looks wrong with the customer date picker, the matrix
  will tell you exactly which day has what available.
- **The cron writes inflows weekly on Mondays.** During testing
  you can force a refresh with
  `php artisan stock:materialize-inflow-schedules`.
