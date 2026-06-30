<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow off-catalog "special request" lines on a restaurant order (wild herbs,
 * pea tops, …): a free-text name with no catalog product. Mirrors
 * {@see 2026_06_28_100000_allow_custom_shop_order_lines}. These lines are always
 * `requested` (reserve no stock) and unpriced at submit — the admin prices them
 * at the invoice step.
 *
 * Unlike shop_order_items, restaurant_order_items.product_id is a real FK
 * (restrictOnDelete), so it must be dropped before changing nullability and
 * re-added afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->string('name_snapshot', 160)->nullable()->after('variant_name_snapshot');
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->dropColumn('name_snapshot');
            // Leave product_id nullable on rollback — re-tightening it would
            // fail if any custom lines exist.
        });
    }
};
