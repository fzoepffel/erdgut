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
            // ISO weekday 0–6 (Monday=0, Sunday=6) to align with Carbon's
            // dayOfWeekIso minus 1 — kept consistent with the form select.
            $table->unsignedTinyInteger('weekday');
            $table->decimal('planned_quantity', 12, 4);
            $table->unsignedTinyInteger('freshness_window_days')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'weekday', 'starts_on']);
            $table->index('ends_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_inflow_schedules');
    }
};
