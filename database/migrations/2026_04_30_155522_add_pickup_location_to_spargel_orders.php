<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial-shop now has two pickup locations the customer can choose
 * from. The chosen location key is recorded on the row so admin
 * fulfilment lists know which person to hand the order to.
 *
 * Nullable because delivery orders never set it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->string('pickup_location', 32)->nullable()->after('delivery_notes');
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn('pickup_location');
        });
    }
};
