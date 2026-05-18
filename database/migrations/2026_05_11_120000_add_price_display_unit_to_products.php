<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 3 for the unit-collapse rebuild.
 *
 * Adds the per-product preferred display unit. After backfill (step 2)
 * every product carries the symbol the admin chose (g/kg, ml/l,
 * piece, bunch). Storage of `products.unit` itself will collapse to
 * the four kinds `mass`/`volume`/`piece`/`bunch` in step 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('price_display_unit', 8)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('price_display_unit');
        });
    }
};
