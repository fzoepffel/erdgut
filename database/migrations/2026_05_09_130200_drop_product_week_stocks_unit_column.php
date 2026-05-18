<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the per-week-row `unit` column.
 *
 * After Phase 1, `Product.unit` is the canonical unit. Letting weekly
 * stock rows carry their own unit allowed admins to set a kilogram-priced
 * product as "10 g this week" by accident and corrupted the box-setup
 * display. Reads now go through the product's unit unconditionally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_week_stocks', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        Schema::table('product_week_stocks', function (Blueprint $table): void {
            $table->string('unit', 32)->nullable()->after('planned_quantity');
        });
    }
};
