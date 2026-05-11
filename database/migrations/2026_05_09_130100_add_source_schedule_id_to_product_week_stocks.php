<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back-pointer from a per-week stock row to the recurring schedule that
 * generated it. Null = manual one-off row.
 *
 * Cancel/adjust on the schedule uses this to know which rows it's safe
 * to delete (matching planned_quantity = "still my row, hasn't been
 * manually overridden"; differing planned_quantity = admin edited it,
 * leave it alone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_week_stocks', function (Blueprint $table): void {
            $table->foreignId('source_schedule_id')
                ->nullable()
                ->after('notes')
                ->constrained('product_week_stock_schedules')
                ->nullOnDelete();
            $table->index('source_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_week_stocks', function (Blueprint $table): void {
            $table->dropForeign(['source_schedule_id']);
            $table->dropIndex(['source_schedule_id']);
            $table->dropColumn('source_schedule_id');
        });
    }
};
