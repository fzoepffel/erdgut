# Inventory rebuild — staging test walkthrough

Do this on staging once all 5 (or 6) phases are merged into `staging`.
The cron parts and the Phase 6 irreversible drop are skipped — those
get verified on prod when we ship.

Open **two browsers**: one logged in as admin (for `/app/admin/...`)
and one incognito for `/spargel`. Many tests need the admin to change
state and immediately re-check the customer side.

**While testing, watch the laravel.log:**

```bash
tail -f storage/logs/laravel-*.log
```

500s or unexpected warnings will surface there in real time.

---

## 1. Data is in place (verify Phase 1 migration worked)

```bash
cd ~/staging
php artisan tinker --execute="
echo 'inflows: ' . DB::table('product_stock_inflows')->count() . PHP_EOL;
echo 'inflow schedules: ' . DB::table('product_stock_inflow_schedules')->count() . PHP_EOL;
echo 'products with daily freshness col: ' . Schema::hasColumn('products', 'default_freshness_window_days') . PHP_EOL;
"
```

Both counts should be positive (data carried over from the previous
weekly model). The column flag should be `1`.

---

## 2. Service-level sanity (Phase 2, no cron)

Manually invoke the materializer once:

```bash
php artisan stock:materialize-inflow-schedules
```

It either reports a number of rows written (extending the 30-week
horizon) or `0` (already up to date). Run it a second time — the
second run should always write `0`.

Probe the service:

```bash
php artisan tinker --execute="
use App\Models\Product;
use App\Services\Admin\InventoryService;
use Illuminate\Support\Carbon;

\$p = Product::where('is_published', true)->first();
if (\$p === null) { echo 'no published products'; exit; }
echo 'product: ' . \$p->name . PHP_EOL;
echo 'available today: ' . app(InventoryService::class)->availableOnDate(\$p, Carbon::today()) . PHP_EOL;
echo 'available next Wed: ' . app(InventoryService::class)->availableOnDate(\$p, Carbon::today()->next(Carbon::WEDNESDAY)) . PHP_EOL;
"
```

Both numbers should return without throwing. They might be `0` —
that's fine. We're confirming the service path works.

---

## 3. Admin Inventar UI (Phase 3)

### 3.1 Page loads

Open `/app/admin/inventory` as admin. Verify:

- Sidebar shows **"Inventar"** (not "Wochen-Katalog").
- Header reads "Inventar".
- Section A "Eingänge" — table of inflow batches for the next 14 days.
- Section B "Verfügbarkeit pro Tag" — product × date matrix.
- Section C "Wiederkehrende Eingänge" — collapsible per-product list.
- Section D "Manueller Eingang" — quick-entry form.

### 3.2 Legacy URL redirects

Visit `/app/admin/products/weekly` → browser should redirect to
`/app/admin/inventory`. Same for `/app/admin/products/weekly/2026-06-08`
→ redirects with `?from=2026-06-08`.

### 3.3 Manual inflow round-trip

In Section D:
- Product: Spargel weiß
- Date: today
- Quantity: `2`
- Freshness: `1`
- Submit.

Verify:
- Green flash "Eingang erfasst".
- Section A now lists today's inflow with the "Manuell" badge.
- Section B's today column for Spargel weiß shows `2`.

### 3.4 Delete inflow

Click "Löschen" on the row you just created. Confirm. Row disappears
and Section B's cell drops back.

### 3.5 Recurring Wednesday schedule

In Section C, expand Spargel weiß:
- Click "Neue Regel anlegen".
- Wochentag: Mittwoch
- Menge: `10`
- Frische-Fenster: `1`
- Ab Datum: next Wednesday
- Submit.

Verify:
- Green flash "Wiederkehrende Lieferung angelegt".
- The schedule appears in Spargel weiß's Section C panel.
- Section A's table now lists multiple upcoming Wednesdays (one per
  week for the next ~26 weeks).
- Section B's Wednesday columns show `10`.

### 3.6 Repeat for Spargel grün

Same as 3.5 but for Spargel grün, also weekday Mittwoch. Both
products now have Wednesday stock.

---

## 4. Spargel customer flow (Phase 4)

### 4.1 Tighten freshness for spargel

Before testing the date picker, set both spargel products to
`default_freshness_window_days = 1` (otherwise the migrated inflows
keep the 7-day freshness and the picker shows whole weeks):

```bash
php artisan tinker --execute="
App\Models\Product::whereIn('id', [
    config('spargel.white_product_id'),
    config('spargel.green_product_id'),
])->update(['default_freshness_window_days' => 1]);

// Also retroactively shorten any existing inflows so the test surfaces
// only the new Wednesday schedule's rows:
App\Models\ProductStockInflow::query()
    ->whereIn('product_id', [
        config('spargel.white_product_id'),
        config('spargel.green_product_id'),
    ])
    ->update(['freshness_window_days' => 1]);

echo 'done';
"
```

### 4.2 Customer-facing date picker

Open `/spargel` (no auth — incognito window).

The date picker should list **only Wednesdays** for the next 8
weeks (assuming you only set up Wednesday schedules in 3.5/3.6).
No Mondays. No Thursdays.

### 4.3 Per-day cap enforcement

Pick the first available Wednesday. Order `4` kg white. Place the
order — should accept.

Reload `/spargel`. Pick the same Wednesday again. The white-spargel
dropdown should max out at `6` kg (10 − 4 already committed).

Try to order `7` kg white — rejected with
"Für diesen Tag sind nur noch 6000 g weißer Spargel verfügbar."

### 4.4 Add a Monday schedule, verify it appears

Back on admin: Section C → Spargel weiß → new rule weekday Montag,
qty `5`, freshness `1`, starts next Monday. Same for Spargel grün.

Reload `/spargel` (incognito). The picker should now also show
Mondays.

### 4.5 Freshness window extends future days

On admin, edit one inflow row in the database to have freshness=3:

```bash
php artisan tinker --execute="
\$wid = config('spargel.white_product_id');
\$gid = config('spargel.green_product_id');
\$nextMon = \Illuminate\Support\Carbon::today()->next(\Illuminate\Support\Carbon::MONDAY)->toDateString();
App\Models\ProductStockInflow::query()
    ->whereIn('product_id', [\$wid, \$gid])
    ->whereDate('received_on', \$nextMon)
    ->update(['freshness_window_days' => 3]);
echo 'next Monday inflows now have freshness=3';
"
```

Reload `/spargel`. The next Monday should now appear AND Tuesday AND
Wednesday (Mon + 0/1/2 days). All three options show available
quantity from the Mon inflow.

### 4.6 Cancel a Wednesday schedule

On admin Inventar Section C → Spargel weiß → cancel the Wednesday
rule starting `next Wednesday + 4 weeks`. Confirm.

Verify on `/spargel` (incognito):
- Next 3 Wednesdays still appear (already materialized inflows
  remain).
- Wednesdays 4+ weeks out vanish.

### 4.7 Full end-to-end order (optional — uses SumUp staging)

Order white spargel for the upcoming Wednesday. Pay via SumUp's
staging cards. Verify:
- Thank-you page renders.
- Confirmation email arrives at your test address.
- Row appears in `/app/spargel-admin`.

---

## 5. Box-setup uses delivery date (Phase 5)

### 5.1 Open box setup

`/app/admin/boxes/setup/<next-monday-iso-date>`. Inspect the
"Available products" panel.

It should list products whose `stockInflows` are active on the
**Wednesday** of that week (today's zone defaults to Wed). Mon-only
inflows with freshness=1 → don't appear. Mon inflows with freshness
≥ 3 days → appear (their window covers Wed).

### 5.2 Resolved delivery date sanity

```bash
php artisan tinker --execute="
use App\Support\BoxDeliveryDateResolver;
\$box = App\Models\Box::query()->latest()->first();
if (\$box === null) { echo 'no boxes'; exit; }
echo 'week_start=' . \$box->week_start_date . PHP_EOL;
echo 'delivery=' . BoxDeliveryDateResolver::deliveryDate(\$box)->toDateString() . PHP_EOL;
"
```

The resolved date should be the Wednesday of the box's week.

### 5.3 Save a box, see commitment in Inventar

Pick a customer, add Spargel weiß × 2 kg to their box, save.

Reload `/app/admin/inventory`. The Wednesday cell for Spargel weiß
should now show 2 kg less than before (the box commitment is
subtracted as outflow).

### 5.4 Old URLs return 404

In a fresh browser tab:
- `/app/admin/products/weekly` → 404 (the Phase-3 redirect was
  removed in Phase 5).
- `/app/admin/products/1/schedules` (or any product ID) → 404.

### 5.5 Legacy command is gone

```bash
php artisan stock:materialize-schedules
```

Should error with "Command not found". Only the new
`stock:materialize-inflow-schedules` works.

---

## 6. End-to-end golden path

| Step | Actor | Action | Verify |
|---|---|---|---|
| 1 | Admin | Inventar Section C → Spargel weiß: Mittwoch 10 kg freshness 1 indefinite | Schedule appears. Section A lists ~26 Wednesdays |
| 2 | Admin | Same for Spargel grün | Both products have Wednesday stock |
| 3 | Admin | Section D: today's harvest, Spargel weiß, 2 kg, freshness 1 | Today in Section A with "Manuell" flag |
| 4 | Customer | `/spargel` (incognito) | Picker shows the manually-added day + Wednesdays for 8 weeks |
| 5 | Customer | Order 5 kg Spargel weiß for next Wednesday | Accepted; thank-you page |
| 6 | Customer | Order another 6 kg Spargel weiß same date | Rejected with per-date cap error |
| 7 | Admin | Refresh Inventar | Section B's next-Wed column for Spargel weiß shows `10 − 5 = 5 kg` available |
| 8 | Admin | Section C → cancel Wednesday rule starting in 4 weeks | Weeks 1–3 stay, weeks 4+ vanish |
| 9 | Customer | Reload `/spargel` | Weeks 1–3 Wednesdays still appear; 4+ gone |
| 10 | Admin | `/app/admin/boxes/setup/<next-monday>` | Spargel weiß listed in available-products panel |

If any step fails: stop, capture the error from the laravel.log, and
report back here.

---

## Notes

- **Section B (the matrix) is the best diagnostic.** If something
  looks off on `/spargel` or in box setup, the matrix tells you
  exactly what the system thinks is available per day.
- **Force the cron** during testing:
  `php artisan stock:materialize-inflow-schedules`.
- **Phase 6 is NOT tested here** — the irreversible drop only runs
  on prod after a week of Phase 1-5 stability. The migration file
  is staged and ready, but don't apply it on staging unless you want
  to fully simulate the prod path.
