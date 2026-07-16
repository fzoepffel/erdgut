<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WYSIWYG receipt editor for B2C orders: per-receipt display overrides
 * (recipient/seller blocks, texts) and a per-item name as printed. Both
 * display-only — money edits go through the shop-order editor, which
 * tracks paid/open amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->json('receipt_overrides')->nullable()->after('outstanding_settled_by_user_id');
        });
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->string('display_name_override', 160)->nullable()->after('name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('receipt_overrides');
        });
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn('display_name_override');
        });
    }
};
