<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a restaurant order back to the standing order that recurs it (null for
 * one-off orders). `original_delivery_date` is the intent key: the materializer
 * dedups on (source_standing_order_id, original_delivery_date) so re-running the
 * scheduled command never creates a second order for the same week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->foreignId('source_standing_order_id')->nullable()->after('id')
                ->constrained('restaurant_standing_orders')->nullOnDelete();
            $table->date('original_delivery_date')->nullable()->after('expected_delivery_date');

            $table->index(['source_standing_order_id', 'original_delivery_date'], 'restaurant_orders_recurrence_idx');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropIndex('restaurant_orders_recurrence_idx');
            $table->dropConstrainedForeignId('source_standing_order_id');
            $table->dropColumn('original_delivery_date');
        });
    }
};
