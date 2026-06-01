<?php

namespace Tests\Feature\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\ShopOrder;
use App\Services\Shop\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Validation, discount math, and redemption-cap bookkeeping for shop coupons.
 *
 * Caps are counted on *finalized* redemptions only: abandoned online
 * checkouts never burn a slot, and a refund/cancel releases it again. The
 * discount applies to the product subtotal only and can never push the
 * charged amount below the SumUp floor (no free-order path).
 */
class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CouponService
    {
        return app(CouponService::class);
    }

    public function test_percent_coupon_discounts_the_subtotal(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'TEN']);

        $result = $this->service()->validateForCheckout('TEN', 'a@example.com', 50.00);

        $this->assertTrue($result->accepted);
        $this->assertSame(5.00, $result->discountEur);
        $this->assertTrue($coupon->is($result->coupon));
    }

    public function test_percent_coupon_is_capped_by_max_discount(): void
    {
        Coupon::factory()->percent(50, maxDiscount: 8.00)->create(['code' => 'HALF']);

        $result = $this->service()->validateForCheckout('HALF', 'a@example.com', 100.00);

        $this->assertTrue($result->accepted);
        $this->assertSame(8.00, $result->discountEur);
    }

    public function test_fixed_coupon_grants_flat_value(): void
    {
        Coupon::factory()->fixed(15, minOrder: 20.00)->create(['code' => 'FIXED15']);

        $result = $this->service()->validateForCheckout('FIXED15', 'a@example.com', 40.00);

        $this->assertTrue($result->accepted);
        $this->assertSame(15.00, $result->discountEur);
    }

    public function test_code_lookup_is_case_insensitive(): void
    {
        Coupon::factory()->percent(10)->create(['code' => 'SUMMER']);

        $result = $this->service()->validateForCheckout('summer', 'a@example.com', 30.00);

        $this->assertTrue($result->accepted);
    }

    public function test_unknown_code_is_collapsed_to_invalid(): void
    {
        $result = $this->service()->validateForCheckout('NOPE', 'a@example.com', 30.00);

        $this->assertFalse($result->accepted);
        $this->assertSame(CouponService::REASON_INVALID, $result->reason);
    }

    public function test_inactive_coupon_is_collapsed_to_invalid(): void
    {
        Coupon::factory()->percent(10)->inactive()->create(['code' => 'OFF']);

        $result = $this->service()->validateForCheckout('OFF', 'a@example.com', 30.00);

        $this->assertFalse($result->accepted);
        $this->assertSame(CouponService::REASON_INVALID, $result->reason);
    }

    public function test_coupon_outside_validity_window_is_invalid(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        Coupon::factory()->percent(10)->create([
            'code' => 'EXPIRED',
            'expires_at' => '2026-05-30 00:00:00',
        ]);

        $result = $this->service()->validateForCheckout('EXPIRED', 'a@example.com', 30.00);

        $this->assertFalse($result->accepted);
        $this->assertSame(CouponService::REASON_INVALID, $result->reason);

        Carbon::setTestNow(null);
    }

    public function test_subtotal_below_minimum_is_rejected_with_min_order_reason(): void
    {
        Coupon::factory()->fixed(15, minOrder: 50.00)->create(['code' => 'BIG']);

        $result = $this->service()->validateForCheckout('BIG', 'a@example.com', 40.00);

        $this->assertFalse($result->accepted);
        $this->assertSame(CouponService::REASON_MIN_ORDER, $result->reason);
        $this->assertSame('50,00', $result->params['min']);
    }

    public function test_discount_is_clamped_so_charged_amount_stays_above_floor(): void
    {
        // Floor defaults to 1.00 — a fixed 30 EUR voucher on a 25 EUR subtotal
        // may only discount down to the floor (25 - 1 = 24).
        Coupon::factory()->fixed(30, minOrder: 20.00)->create(['code' => 'NEARLY']);

        $result = $this->service()->validateForCheckout('NEARLY', 'a@example.com', 25.00);

        $this->assertTrue($result->accepted);
        $this->assertSame(24.00, $result->discountEur);
    }

    public function test_global_cap_blocks_when_exhausted(): void
    {
        Coupon::factory()->percent(10)->create([
            'code' => 'ONCE',
            'max_redemptions' => 1,
            'redeemed_count' => 1,
        ]);

        $result = $this->service()->validateForCheckout('ONCE', 'a@example.com', 30.00);

        $this->assertFalse($result->accepted);
        $this->assertSame(CouponService::REASON_LIMIT_REACHED, $result->reason);
    }

    public function test_per_email_limit_blocks_second_use_by_same_email(): void
    {
        $coupon = Coupon::factory()->percent(10)->create([
            'code' => 'PEREMAIL',
            'per_email_limit' => 1,
        ]);

        // One finalized redemption already exists for this email.
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'email' => 'repeat@example.com',
            'discount_eur' => 3.00,
            'status' => CouponRedemption::STATUS_REDEEMED,
            'redeemed_at' => now(),
        ]);

        $blocked = $this->service()->validateForCheckout('PEREMAIL', 'repeat@example.com', 30.00);
        $this->assertFalse($blocked->accepted);
        $this->assertSame(CouponService::REASON_LIMIT_REACHED, $blocked->reason);

        // A different email still works.
        $ok = $this->service()->validateForCheckout('PEREMAIL', 'fresh@example.com', 30.00);
        $this->assertTrue($ok->accepted);
    }

    public function test_voided_redemptions_do_not_count_against_per_email_limit(): void
    {
        $coupon = Coupon::factory()->percent(10)->create([
            'code' => 'VOIDOK',
            'per_email_limit' => 1,
        ]);

        // A previously voided (refunded) redemption must not block re-use.
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'email' => 'refunded@example.com',
            'discount_eur' => 3.00,
            'status' => CouponRedemption::STATUS_VOID,
            'redeemed_at' => now(),
        ]);

        $result = $this->service()->validateForCheckout('VOIDOK', 'refunded@example.com', 30.00);

        $this->assertTrue($result->accepted);
    }

    public function test_finalize_redemption_records_and_increments_cap(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'FIN']);
        $order = ShopOrder::factory()->create([
            'email' => 'buyer@example.com',
            'coupon_id' => $coupon->id,
            'coupon_code' => 'FIN',
            'discount_eur' => 4.00,
        ]);

        $this->service()->finalizeRedemption($order);

        $this->assertSame(1, $coupon->fresh()->redeemed_count);
        $this->assertDatabaseHas('coupon_redemptions', [
            'shop_order_id' => $order->id,
            'status' => CouponRedemption::STATUS_REDEEMED,
            'email' => 'buyer@example.com',
        ]);
    }

    public function test_finalize_redemption_is_idempotent(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'IDEM']);
        $order = ShopOrder::factory()->create([
            'email' => 'buyer@example.com',
            'coupon_id' => $coupon->id,
            'coupon_code' => 'IDEM',
            'discount_eur' => 4.00,
        ]);

        $this->service()->finalizeRedemption($order);
        $this->service()->finalizeRedemption($order);

        $this->assertSame(1, $coupon->fresh()->redeemed_count);
        $this->assertSame(1, CouponRedemption::where('shop_order_id', $order->id)->count());
    }

    public function test_void_redemption_releases_the_slot(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'REL']);
        $order = ShopOrder::factory()->create([
            'email' => 'buyer@example.com',
            'coupon_id' => $coupon->id,
            'coupon_code' => 'REL',
            'discount_eur' => 4.00,
        ]);

        $this->service()->finalizeRedemption($order);
        $this->assertSame(1, $coupon->fresh()->redeemed_count);

        $this->service()->voidRedemption($order);

        $this->assertSame(0, $coupon->fresh()->redeemed_count);
        $this->assertDatabaseHas('coupon_redemptions', [
            'shop_order_id' => $order->id,
            'status' => CouponRedemption::STATUS_VOID,
        ]);
    }

    public function test_abandoned_online_checkout_does_not_burn_the_cap(): void
    {
        // An order that never reaches Paid never gets finalized, so the cap
        // stays untouched and the code is still redeemable by others.
        $coupon = Coupon::factory()->percent(10)->create([
            'code' => 'ABANDON',
            'max_redemptions' => 1,
        ]);
        ShopOrder::factory()->create([
            'email' => 'ghost@example.com',
            'coupon_id' => $coupon->id,
            'coupon_code' => 'ABANDON',
            'discount_eur' => 4.00,
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $this->assertSame(0, $coupon->fresh()->redeemed_count);
        $this->assertTrue(
            $this->service()->validateForCheckout('ABANDON', 'other@example.com', 30.00)->accepted
        );
    }

    public function test_paid_order_finalizes_redemption_via_observer(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'OBSERVE']);
        $order = ShopOrder::factory()->create([
            'email' => 'paid@example.com',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
            'coupon_id' => $coupon->id,
            'coupon_code' => 'OBSERVE',
            'discount_eur' => 4.00,
        ]);

        $order->update(['status' => ShopOrderStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(1, $coupon->fresh()->redeemed_count);
    }

    public function test_refunding_paid_order_voids_redemption_via_observer(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'REFUND']);
        $order = ShopOrder::factory()->create([
            'email' => 'paid@example.com',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
            'coupon_id' => $coupon->id,
            'coupon_code' => 'REFUND',
            'discount_eur' => 4.00,
        ]);

        $order->update(['status' => ShopOrderStatus::Paid, 'paid_at' => now()]);
        $this->assertSame(1, $coupon->fresh()->redeemed_count);

        $order->update(['status' => ShopOrderStatus::Refunded]);

        $this->assertSame(0, $coupon->fresh()->redeemed_count);
    }
}
