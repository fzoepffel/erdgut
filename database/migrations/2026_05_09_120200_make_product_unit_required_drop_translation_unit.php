<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finalize the unit migration:
 *  - Make `products.unit` NOT NULL (backfill ran in the previous step).
 *  - Drop the now-redundant `product_translations.unit` column.
 *
 * Step 3 of 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safety net: if any product still has a NULL unit (e.g. created
        // between the backfill migration and this one), default it now.
        DB::table('products')->whereNull('unit')->update(['unit' => 'piece']);

        Schema::table('products', function (Blueprint $table): void {
            $table->string('unit', 16)->nullable(false)->change();
        });

        Schema::table('product_translations', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->string('unit', 32)->nullable()->after('name');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('unit', 16)->nullable()->change();
        });
    }
};
