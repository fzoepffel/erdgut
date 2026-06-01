<?php

use App\Enums\CouponType;
use App\Models\ShopOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed discount coupons for the anonymous shop.
 *
 * A coupon reduces the *product subtotal only* (never the delivery fee) of
 * a {@see ShopOrder} at checkout. Two kinds (see
 * {@see CouponType}): a `percent` off the subtotal (optionally
 * capped by `max_discount_eur`) or a `fixed` EUR amount (which usually
 * requires a `min_order_eur` so a small order can't go free).
 *
 * Anti-abuse is two-layered: `per_email_limit` caps how many *finalized*
 * orders a single email may redeem the code on, and the optional
 * `max_redemptions` caps total finalized redemptions across all customers
 * (null = unlimited). `redeemed_count` is the running tally of finalized
 * redemptions, maintained under a row lock so concurrent paid orders can't
 * both slip past the cap. There is intentionally no 100%-off path — combined
 * with the fixed-voucher minimum, an order's charged total can never reach
 * zero, so every order still settles through SumUp normally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Public code the guest types; stored uppercased + unique so
            // "AGICAP" and "agicap" can never collide as two coupons.
            $table->string('code')->unique();

            // Internal label for the admin list (e.g. "Agicap B2B").
            $table->string('label')->nullable();

            // percent | fixed (App\Enums\CouponType).
            $table->string('type');

            // For percent: the rate (e.g. 10.00 = 10%). For fixed: the EUR
            // amount off. Validation enforces percent < 100 (no free orders).
            $table->decimal('value', 8, 2);

            // Optional cap on a percent coupon's EUR discount.
            $table->decimal('max_discount_eur', 8, 2)->nullable();

            // Minimum product subtotal (EUR) required for the coupon to
            // apply. Fixed coupons usually set this so they can't zero out a
            // tiny order.
            $table->decimal('min_order_eur', 8, 2)->nullable();

            // Global cap on finalized redemptions (null = unlimited).
            $table->unsignedInteger('max_redemptions')->nullable();

            // How many finalized orders a single email may redeem on.
            $table->unsignedInteger('per_email_limit')->default(1);

            // Running tally of finalized redemptions (maintained under lock).
            $table->unsignedInteger('redeemed_count')->default(0);

            // Optional validity window.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
