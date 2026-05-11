<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `products.unit_price` from decimal(10,2) to decimal(12,4).
 *
 * Phase 4 (2026-05-09) introduces natural-unit pricing: admins enter
 * the price per natural display unit (€/kg, €/L, €/piece) and the form
 * computes the per-storage-unit price (€/g, €/ml, €/piece) before
 * persist. For products stored in grams (e.g. spargel), the storage
 * price needs 4 decimals — 21.50 €/kg becomes 0.0215 €/g.
 *
 * Existing values (e.g. 2.49) round-trip unchanged: decimal(12,4)
 * still stores 2.4900.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('unit_price', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('unit_price', 10, 2)->change();
        });
    }
};
