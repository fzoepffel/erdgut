<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT snapshot for the delivery fee on a shop order.
 *
 * The delivery fee is itself a VAT-bearing service. Its rate follows the
 * dominant rate of the basket (resolved in ShopOrderService::quote); the
 * amount is the VAT contained in the gross `delivery_fee_eur`. Snapshotted
 * alongside the line-item VAT so the per-rate breakdown on receipts is
 * complete and immutable.
 *
 * Existing rows with a delivery fee are backfilled at 7%; defaults (7 / 0)
 * are kept as a safety net. Pickup orders keep amount 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('delivery_fee_vat_rate_percent')->default(7)->after('delivery_fee_eur');
            $table->decimal('delivery_fee_vat_amount_eur', 8, 2)->default(0)->after('delivery_fee_vat_rate_percent');
        });

        DB::statement('UPDATE shop_orders SET delivery_fee_vat_rate_percent = 7, delivery_fee_vat_amount_eur = ROUND(delivery_fee_eur * 7 / 107, 2) WHERE delivery_fee_eur > 0');
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee_vat_rate_percent', 'delivery_fee_vat_amount_eur']);
        });
    }
};
