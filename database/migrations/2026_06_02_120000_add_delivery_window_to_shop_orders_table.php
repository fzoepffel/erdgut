<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-order delivery / pickup time window, set by the admin from the
 * shop-orders list and surfaced on the customer tracking page + email.
 *
 * `delivery_window_set_at` records when the window was last published so the
 * admin save can skip re-emailing the customer on an unchanged save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->time('delivery_window_from')->nullable()->after('expected_delivery_date');
            $table->time('delivery_window_to')->nullable()->after('delivery_window_from');
            $table->timestamp('delivery_window_set_at')->nullable()->after('delivery_window_to');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_window_from', 'delivery_window_to', 'delivery_window_set_at']);
        });
    }
};
