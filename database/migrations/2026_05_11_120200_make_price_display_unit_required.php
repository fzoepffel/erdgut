<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 of 3 for the unit-collapse rebuild.
 *
 *  - Safety-net any rows that snuck in between step 2 and now without a
 *    `price_display_unit` (e.g. test seeds), defaulting to the kind's
 *    canonical small unit.
 *  - Tighten the column to NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNull('price_display_unit')->where('unit', 'mass')->update(['price_display_unit' => 'kg']);
        DB::table('products')->whereNull('price_display_unit')->where('unit', 'volume')->update(['price_display_unit' => 'l']);
        DB::table('products')->whereNull('price_display_unit')->where('unit', 'piece')->update(['price_display_unit' => 'piece']);
        DB::table('products')->whereNull('price_display_unit')->where('unit', 'bunch')->update(['price_display_unit' => 'bunch']);
        DB::table('products')->whereNull('price_display_unit')->update(['price_display_unit' => 'piece']);

        Schema::table('products', function (Blueprint $table): void {
            $table->string('price_display_unit', 8)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('price_display_unit', 8)->nullable()->change();
        });
    }
};
