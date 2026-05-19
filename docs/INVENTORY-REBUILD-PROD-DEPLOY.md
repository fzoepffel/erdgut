# Inventory rebuild — production deploy checklist

Run this from top to bottom each time you merge a phase from `staging`
to `main`. Each phase has its own "what to run on the server" section.

Phases are independent in the sense that each is its own merge to
main. They MUST ship in order: 1 → 2 → 3 → 4 → 5 → (wait 1 week) → 6.

---

## Pre-deploy (one-time, before Phase 1)

**Take a fresh database backup.** This is the only safe rollback for
Phase 6 later, so don't skip.

```bash
# SSH into prod
cd ~/erdgut
mysqldump -u dbo15452148 -p dbs15452148 | gzip > ~/backups/erdgut-pre-inventory-rebuild-$(date +%Y%m%d-%H%M).sql.gz
ls -la ~/backups/ | tail
```

Confirm `SPARGEL_PRODUCT_ID_*` env vars are still set:

```bash
grep -E 'SPARGEL_PRODUCT_ID_|DELIVERY_DEFAULT' .env
```

Note pre-deploy counts (so you can sanity-check after migrations):

```bash
php artisan tinker --execute="
echo 'orders: ' . App\Models\SpargelOrder::count() . PHP_EOL;
echo 'weekly stocks: ' . DB::table('product_week_stocks')->count() . PHP_EOL;
echo 'weekly schedules: ' . DB::table('product_week_stock_schedules')->count() . PHP_EOL;
"
```

---

## Phase 1 — Schema + data migration

Run after `staging` → `main` merge of `feature/erd-inventory-phase-1-daily-inflows`.

```bash
# SSH into prod
cd ~/erdgut
php artisan migrate --force
```

Expected output: 4 migrations applied (`120000`, `120100`, `120200`, `120300`).

**Verify:**

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

Counts must match (`weekly == daily`). **Stop and report if they
diverge.**

Smoke test: `/app/admin/products/weekly` still renders (Phase 1
doesn't change the UI). Place a test spargel order via `/spargel` —
still works.

---

## Phase 2 — Service layer + cron rename

Run after merging `feature/erd-inventory-phase-2-service`.

**No migration.** Code-only deploy. The deploy workflow's
`php artisan config:clear` handles cache invalidation.

**Verify the cron entry:**

```bash
php artisan schedule:list | grep stock:
```

Expect `stock:materialize-inflow-schedules` scheduled on Mondays
03:00.

**Optional: trigger the materializer once now.** Otherwise it runs
next Monday automatically.

```bash
php artisan stock:materialize-inflow-schedules
```

Reports how many rows were written. Re-running should write `0`.

The legacy `stock:materialize-schedules` still works as a shim that
forwards to the new command — Phase 5 removes it.

---

## Phase 3 — Admin Inventar UI

Run after merging `feature/erd-inventory-phase-3-admin-ui`.

**No migration.** Code-only deploy.

Smoke check: open `/app/admin/inventory`. The 4-section page
should render. Sidebar shows "Inventar" (was "Wochen-Katalog").

The legacy URL `/app/admin/products/weekly` 302-redirects to
`/app/admin/inventory` — bookmarks keep working.

---

## Phase 4 — Spargel customer migration

Run after merging `feature/erd-inventory-phase-4-spargel-customer`.

**No migration.** Code-only deploy.

Verify the spargel product IDs are still configured:

```bash
grep -E 'SPARGEL_PRODUCT_ID_' .env
```

**Set the freshness to 1 day on both spargel products** (otherwise
they keep the migrated 7-day freshness from Phase 1, which means
the picker shows the full week instead of just inflow days):

```bash
php artisan tinker --execute="
App\Models\Product::whereIn('id', [
    config('spargel.white_product_id'),
    config('spargel.green_product_id'),
])->update(['default_freshness_window_days' => 1]);
echo 'spargel freshness set to 1';
"
```

(Existing migrated inflows still carry freshness=7 — that's fine;
they cover the week as before. The 1-day default applies to NEW
inflows and schedules going forward. To shorten the existing rows
too, run `App\Models\ProductStockInflow::query()->whereIn('product_id', [...])->update(['freshness_window_days' => 1]);`)

Smoke check: open `/spargel` (no auth). The date picker only shows
dates where both spargel products have positive availability.

---

## Phase 5 — Box-setup migration + legacy deletes

Run after merging `feature/erd-inventory-phase-5-box-setup`.

**No migration.** Code-only deploy.

After this phase:
- The legacy `stock:materialize-schedules` command is GONE. The
  cron entry in `bootstrap/app.php` already points at the new name;
  if you have any custom cron entry on the IONOS panel that calls
  the old name, update it now.
- The routes `/app/admin/products/weekly` and `/products/{p}/schedules/*`
  return 404. Bookmarks break — communicate the move if anyone uses
  them.

Smoke: `/app/admin/inventory` loads, `/spargel` loads, place one
test box edit via `/app/admin/boxes/setup/<next-monday>`.

---

## Phase 6 — Drop legacy tables

**⚠️ Wait at least one full week after Phase 5 lands on prod.** This
migration is irreversible at the data level — `down()` only recreates
empty shells.

Pre-flight: take a fresh DB backup (last chance for recovery):

```bash
mysqldump -u dbo15452148 -p dbs15452148 | gzip > ~/backups/erdgut-pre-phase-6-$(date +%Y%m%d-%H%M).sql.gz
```

Verify daily inventory is in healthy state (inflows should be ≥ what
Phase 1 wrote since the cron has been adding more):

```bash
php artisan tinker --execute="
echo 'inflows: ' . DB::table('product_stock_inflows')->count() . PHP_EOL;
echo 'inflow schedules: ' . DB::table('product_stock_inflow_schedules')->count() . PHP_EOL;
"
```

**Stop if inflows is 0** — that means the rebuild isn't actually
active and dropping the legacy tables loses real data.

Then deploy + migrate:

```bash
cd ~/erdgut
php artisan migrate --force
```

Verify the tables are gone:

```bash
php artisan tinker --execute="
echo 'product_week_stocks exists: ' . (Schema::hasTable('product_week_stocks') ? 'YES' : 'no') . PHP_EOL;
echo 'product_week_stock_schedules exists: ' . (Schema::hasTable('product_week_stock_schedules') ? 'YES' : 'no') . PHP_EOL;
"
```

Both print `no`. Smoke-test customer + admin paths once more.

---

## Rollback procedure

**Pre-Phase-6:** the legacy tables still exist with their original
data.

1. Revert the relevant commits on `main`.
2. Re-deploy.
3. `php artisan migrate:rollback --step=4` (Phase 1) — the new tables
   disappear, old tables keep their data.

**Post-Phase-6:** restore the database from the backup taken in the
pre-flight step. The dropped tables can't be recovered from
migrations.

---

## What to communicate to other admins

Once each phase is live:

- **After Phase 3:** "The Wochenkatalog is now called 'Inventar' —
  same admin sidebar position, just renamed. Click any product to
  manage its recurring inflows."
- **After Phase 4:** "Customers can now book spargel for any day
  where we have stock — not just Mondays. To open up a Wednesday,
  add a Wednesday inflow schedule in the Inventar page."
- **After Phase 5:** "Old bookmarks like `/products/weekly` will
  404 now — use `/inventory` instead."
