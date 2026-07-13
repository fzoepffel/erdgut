<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-farm buying price: what Erdgut pays THIS farm for the product.
 * Same semantics as products.buying_price_cents (display-unit euros
 * times 100, e.g. €/kg → cents). NULL means the product's default
 * buying price applies for this farm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_producer', function (Blueprint $table): void {
            $table->unsignedInteger('buying_price_cents')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('product_producer', function (Blueprint $table): void {
            $table->dropColumn('buying_price_cents');
        });
    }
};
