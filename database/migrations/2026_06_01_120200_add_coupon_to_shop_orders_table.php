<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attach an optional coupon to a shop order.
 *
 * `coupon_id` links the applied coupon (kept even if the coupon is later
 * deleted? no — null on delete so history stays queryable). `coupon_code`
 * snapshots the code typed at checkout so the receipt/admin still shows it
 * if the coupon row is gone. `discount_eur` is the EUR taken off the
 * product subtotal; `total_amount_eur` already reflects it, this column
 * just makes the breakdown explicit on receipts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('total_amount_eur')
                ->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->decimal('discount_eur', 8, 2)->default(0)->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'discount_eur']);
        });
    }
};
