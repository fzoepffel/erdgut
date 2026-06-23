<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            // Locale the order was placed in, so customer documents (receipt,
            // confirmation email) render in the customer's language regardless
            // of who/what triggers them later (e.g. an admin downloading the
            // receipt from an English admin session).
            $table->string('locale', 5)->nullable()->after('customer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
