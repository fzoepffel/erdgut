<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per *finalized* coupon redemption.
 *
 * A redemption is written only when an order becomes a confirmed sale —
 * online orders when SumUp settles (status → Paid), in-person orders at
 * creation. This is what keeps abandoned online checkouts from burning a
 * coupon's caps: no payment, no redemption row, no consumed slot.
 *
 * The row carries the normalized `email` so the per-email limit can be
 * counted, and the `discount_eur` actually granted (snapshot). `status`
 * lets a later refund/cancel `void` the redemption and release the slot
 * back to the coupon's caps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('shop_order_id')->nullable()->constrained('shop_orders')->nullOnDelete();

            // Normalized (lowercased, trimmed) email of the redeeming guest.
            $table->string('email');

            // EUR discount actually granted on this order (snapshot).
            $table->decimal('discount_eur', 8, 2);

            // redeemed | void — void releases the slot after a refund/cancel.
            $table->string('status')->default('redeemed');

            $table->timestamp('redeemed_at')->nullable();

            $table->timestamps();

            $table->index(['coupon_id', 'email']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
