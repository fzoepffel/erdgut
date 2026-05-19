<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 of the daily-inventory rebuild: drop the legacy tables.
 *
 * After Phase 5 lands and stabilizes, nothing reads or writes to
 * `product_week_stocks` or `product_week_stock_schedules`. The data
 * was carried over to `product_stock_inflows` and
 * `product_stock_inflow_schedules` by the Phase 1 data migration.
 *
 * `down()` recreates the tables EMPTY as a defensive rollback path —
 * no data backfill, because the canonical state now lives in the
 * daily-inventory tables. If a real rollback is ever needed, restore
 * the database from a backup taken before this migration ran.
 *
 * The order of drops matters: `product_week_stocks.source_schedule_id`
 * has a foreign-key constraint pointing at
 * `product_week_stock_schedules.id`, so drop the inflows-side table
 * first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_week_stocks');
        Schema::dropIfExists('product_week_stock_schedules');
    }

    public function down(): void
    {
        Schema::create('product_week_stock_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_quantity', 10, 2);
            $table->date('starts_on_monday');
            $table->date('ends_on_monday')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'starts_on_monday']);
            $table->index('ends_on_monday');
        });

        Schema::create('product_week_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->decimal('planned_quantity', 10, 2)->default(0);
            $table->foreignId('source_schedule_id')
                ->nullable()
                ->constrained('product_week_stock_schedules')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'week_start_date']);
            $table->index('week_start_date');
        });
    }
};
