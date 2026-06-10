<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product VAT rate (German "Mehrwertsteuersatz").
 *
 * German tax law applies different VAT rates by product kind: 7% (reduced,
 * § 12 Abs. 2 UStG) for fresh food — produce, meat, fish, dairy, eggs,
 * bread — and 19% (standard) for processed goods such as bottled juices
 * and drinks. Until now the shop assumed a single 7% rate via config.
 *
 * Stored as a whole-percent integer; the gross `unit_price` already
 * *includes* this VAT (prices are consumer-facing gross). Defaulting every
 * existing row to 7 matches the prior global assumption with no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('vat_rate_percent')->default(7)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('vat_rate_percent');
        });
    }
};
