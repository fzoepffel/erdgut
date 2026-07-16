<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WYSIWYG invoice editor: per-invoice display overrides. The JSON column
 * holds whitelisted text overrides (recipient/seller/bank blocks, terms);
 * the per-item column carries the name AS PRINTED on this one invoice
 * (products are sometimes billed under a slightly different name). Both
 * are display-only — money and order data stay in their real columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->json('invoice_overrides')->nullable()->after('invoice_paid_at');
        });
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->string('display_name_override', 160)->nullable()->after('name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn('invoice_overrides');
        });
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            $table->dropColumn('display_name_override');
        });
    }
};
