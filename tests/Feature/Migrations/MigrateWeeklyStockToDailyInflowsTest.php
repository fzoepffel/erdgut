<?php

namespace Tests\Feature\Migrations;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 data migration: weekly stock + schedules → daily inflows
 * + inflow schedules.
 *
 * RefreshDatabase has already applied every migration once (against
 * an empty legacy table set, so the new tables are empty by default).
 * Each test below seeds legacy rows and re-runs only the data
 * migration's `up()` to exercise the conversion path.
 */
class MigrateWeeklyStockToDailyInflowsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = '/database/migrations/2026_05_18_120300_migrate_weekly_stock_to_daily_inflows.php';

    private function runDataMigration(): void
    {
        $migration = require base_path(self::MIGRATION_PATH);
        $migration->up();
    }

    public function test_each_weekly_stock_row_becomes_one_inflow_with_seven_day_freshness(): void
    {
        $product = Product::factory()->create();

        DB::table('product_week_stocks')->insert([
            'product_id' => $product->id,
            'week_start_date' => '2026-05-25',
            'planned_quantity' => 10000.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runDataMigration();

        $this->assertDatabaseHas('product_stock_inflows', [
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 10000.0,
            'freshness_window_days' => 7,
        ]);
        $this->assertSame(1, DB::table('product_stock_inflows')->count());
    }

    public function test_each_weekly_schedule_becomes_one_monday_inflow_schedule(): void
    {
        $product = Product::factory()->create();

        DB::table('product_week_stock_schedules')->insert([
            'product_id' => $product->id,
            'planned_quantity' => 10000.0,
            'starts_on_monday' => '2026-05-04',
            'ends_on_monday' => null,
            'notes' => 'Max weekly',
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runDataMigration();

        $this->assertDatabaseHas('product_stock_inflow_schedules', [
            'product_id' => $product->id,
            'weekday' => 0, // Monday
            'planned_quantity' => 10000.0,
            'freshness_window_days' => 7,
            'starts_on' => '2026-05-04',
            'ends_on' => null,
        ]);
    }

    public function test_inflow_source_schedule_id_is_remapped_to_the_new_schedule_id(): void
    {
        $product = Product::factory()->create();

        $oldScheduleId = DB::table('product_week_stock_schedules')->insertGetId([
            'product_id' => $product->id,
            'planned_quantity' => 10000.0,
            'starts_on_monday' => '2026-05-04',
            'ends_on_monday' => null,
            'notes' => null,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_week_stocks')->insert([
            'product_id' => $product->id,
            'week_start_date' => '2026-05-04',
            'planned_quantity' => 10000.0,
            'source_schedule_id' => $oldScheduleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runDataMigration();

        $newScheduleId = DB::table('product_stock_inflow_schedules')->value('id');
        $this->assertNotNull($newScheduleId);

        $inflow = DB::table('product_stock_inflows')->first();
        $this->assertNotNull($inflow);
        $this->assertSame((int) $newScheduleId, (int) $inflow->source_schedule_id);
    }

    public function test_migration_is_idempotent_on_rerun(): void
    {
        $product = Product::factory()->create();

        DB::table('product_week_stocks')->insert([
            'product_id' => $product->id,
            'week_start_date' => '2026-05-25',
            'planned_quantity' => 10000.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_week_stock_schedules')->insert([
            'product_id' => $product->id,
            'planned_quantity' => 5000.0,
            'starts_on_monday' => '2026-05-25',
            'ends_on_monday' => null,
            'notes' => null,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runDataMigration();
        $this->runDataMigration();

        $this->assertSame(1, DB::table('product_stock_inflows')->count());
        $this->assertSame(1, DB::table('product_stock_inflow_schedules')->count());
    }
}
