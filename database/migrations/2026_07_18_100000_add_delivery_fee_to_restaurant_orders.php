<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional delivery fee on B2B invoices: entered NET in the admin pricing
 * step and rendered as its own "Lieferung <address>" line on the Rechnung.
 * Null means no fee (the historical default — restaurant orders never
 * carried one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->decimal('delivery_fee_net_eur', 8, 2)->nullable()->after('vat_total_eur');
            $table->unsignedTinyInteger('delivery_fee_vat_rate_percent')->nullable()->after('delivery_fee_net_eur');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee_net_eur', 'delivery_fee_vat_rate_percent']);
        });
    }
};
