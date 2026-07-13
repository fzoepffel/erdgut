<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The producer moves to the batch: every inflow (and its generating
 * schedule) knows which farm delivered it. Nullable so pre-existing rows
 * stay valid until the backfill migration stamps them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_stock_inflows', 'producer_id')) {
            Schema::table('product_stock_inflows', function (Blueprint $table) {
                $table->foreignId('producer_id')->nullable()->after('variant_id')
                    ->constrained('producers')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('product_stock_inflow_schedules', 'producer_id')) {
            Schema::table('product_stock_inflow_schedules', function (Blueprint $table) {
                $table->foreignId('producer_id')->nullable()->after('variant_id')
                    ->constrained('producers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_stock_inflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producer_id');
        });
        Schema::table('product_stock_inflow_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producer_id');
        });
    }
};
