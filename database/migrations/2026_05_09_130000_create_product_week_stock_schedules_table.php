<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring weekly-stock plans, per product.
 *
 * Source-of-truth for "this product is 10 kg/week, indefinitely from
 * week X". Materialized into `product_week_stocks` rows by
 * RecurringStockService at write time + by a weekly cron that extends
 * the window. Read paths never query this table — they keep using
 * `product_week_stocks` unchanged.
 */
return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('product_week_stock_schedules');
    }
};
