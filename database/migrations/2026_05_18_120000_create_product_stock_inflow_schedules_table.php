<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring per-weekday inflow schedules — the daily-inventory
 * replacement for `product_week_stock_schedules`.
 *
 * A schedule expresses "this product is received in $planned_quantity
 * every $weekday, starting on $starts_on, until $ends_on (or
 * indefinitely)". Each schedule belongs to one weekday only; a
 * producer who delivers on Mon AND Wed gets two schedules.
 *
 * `freshness_window_days` is captured at the schedule level so
 * materialized inflow rows inherit it. Mirror copy on inflow rows
 * lets admin override per receipt.
 *
 * Source-of-truth for materialization (handled by InventoryService);
 * read paths query `product_stock_inflows` directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_inflow_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Carbon `dayOfWeek` convention: 0=Sunday, 1=Monday, …,
            // 6=Saturday. Matches `Subscription.delivery_weekday` and
            // `config('delivery.zones.*.allowed_weekdays')` so Phase 5's
            // box-delivery-date resolution can compare these numbers
            // directly without translation.
            $table->unsignedTinyInteger('weekday');
            $table->decimal('planned_quantity', 12, 4);
            $table->unsignedTinyInteger('freshness_window_days')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short names: the auto-generated
            // `product_stock_inflow_schedules_product_id_weekday_starts_on_index`
            // is 65 chars and exceeds MySQL's 64-char identifier limit.
            // The ends_on single-column index is fine but we name it
            // too for consistency.
            $table->index(['product_id', 'weekday', 'starts_on'], 'inflow_schedules_product_weekday_starts_idx');
            $table->index('ends_on', 'inflow_schedules_ends_on_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_inflow_schedules');
    }
};
