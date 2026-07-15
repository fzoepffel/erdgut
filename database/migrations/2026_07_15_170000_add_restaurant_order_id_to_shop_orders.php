<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant orders that shipped through the admin request flow get a mirror
 * ShopOrder record so the admin sees them in the unified shop-orders list
 * and can use the same edit / fulfilment surface as native shop orders. The
 * mirror carries restaurant_order_id back to the source; the source remains
 * as the "as-requested" history and stops being counted in revenue (see
 * AdminOperationsMetrics::b2bByDate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->foreignId('restaurant_order_id')->nullable()->after('customer_type')
                ->constrained('restaurant_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_order_id');
        });
    }
};
