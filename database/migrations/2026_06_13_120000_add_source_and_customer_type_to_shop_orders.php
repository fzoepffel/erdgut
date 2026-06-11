<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds two classification columns to shop_orders:
 *
 * - order_source: who placed the order — 'storefront' (customer self-served)
 *   or 'admin' (staff entered it manually after direct contact).
 * - customer_type: 'private' (B2C individual) or 'business' (B2B —
 *   restaurant, caterer, institution, etc.).
 *
 * Existing rows receive the defaults via column default — no backfill needed.
 * Both columns are indexed so the admin list can filter/report on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('order_source')->default('storefront')->after('is_test');
            $table->string('customer_type')->default('private')->after('order_source');

            $table->index('order_source');
            $table->index('customer_type');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropIndex(['order_source']);
            $table->dropIndex(['customer_type']);
            $table->dropColumn(['order_source', 'customer_type']);
        });
    }
};
