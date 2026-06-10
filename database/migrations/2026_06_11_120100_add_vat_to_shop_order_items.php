<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VAT snapshot on each shop order line.
 *
 * Like `unit_price_snapshot_eur`, the VAT rate and the computed VAT amount
 * are frozen at order time so a later catalog edit (e.g. moving a product
 * from 7% to 19%) can never rewrite the tax record of an already-placed
 * order. `vat_amount_eur` is the VAT *contained* in the gross
 * `line_total_eur` (prices are gross): amount = gross * rate / (100 + rate).
 *
 * Existing rows are backfilled at 7% (the prior global rate). The column
 * defaults (7 / 0) are kept as a harmless safety net; the ShopOrderService
 * always writes both values explicitly from the product's rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('vat_rate_snapshot_percent')->default(7)->after('line_total_eur');
            $table->decimal('vat_amount_eur', 8, 2)->default(0)->after('vat_rate_snapshot_percent');
        });

        // Backfill historical rows at the previously-assumed 7% rate. The VAT
        // is the tax contained in the gross line total: gross * 7 / 107.
        DB::statement('UPDATE shop_order_items SET vat_rate_snapshot_percent = 7, vat_amount_eur = ROUND(line_total_eur * 7 / 107, 2)');
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn(['vat_rate_snapshot_percent', 'vat_amount_eur']);
        });
    }
};
