<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-batch inflow rows — every physical receipt of a product
 * (manual one-off or materialized from a recurring schedule) lives
 * here as its own row.
 *
 * Availability on date D is computed as the sum of `quantity` across
 * rows where `received_on <= D <= received_on + (freshness_window_days
 * - 1)`. Outflows are computed on the fly from spargel orders +
 * box_items, not stored as rows yet (see plan).
 *
 * `source_schedule_id` back-points to the schedule that produced
 * the row. Manual one-offs leave it NULL. The cancel/adjust flow on
 * the schedule side uses this column to skip rows it didn't create.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_inflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('received_on');
            $table->decimal('quantity', 12, 4);
            $table->unsignedTinyInteger('freshness_window_days')->default(1);
            $table->foreignId('source_schedule_id')
                ->nullable()
                ->constrained('product_stock_inflow_schedules')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_inflows');
    }
};
