<?php

namespace Tests\Unit\Models;

use App\Enums\ShopOrderStatus;
use App\Models\ShopOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Derived open-amount state for edited orders: what was collected stays
 * collected, the difference to the current total is the open remainder
 * (positive = collect in person, negative = refund in person).
 */
class ShopOrderOutstandingAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_never_paid_order_has_no_open_amount(): void
    {
        $order = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::PendingPayment,
            'payment_method' => 'in_person',
            'total_amount_eur' => 20.00,
            'paid_at' => null,
        ]);

        $this->assertNull($order->effectivePaidAmountEur());
        $this->assertSame(0.0, $order->outstandingAmountEur());
        $this->assertFalse($order->hasOpenAmount());
    }

    public function test_legacy_paid_order_without_snapshot_reads_total_as_paid(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'total_amount_eur' => 34.50,
            'paid_amount_eur' => null,
        ]);

        $this->assertSame(34.5, $order->effectivePaidAmountEur());
        $this->assertSame(0.0, $order->outstandingAmountEur());
        $this->assertFalse($order->hasOpenAmount());
    }

    public function test_raised_total_yields_positive_outstanding(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'total_amount_eur' => 40.00,
            'paid_amount_eur' => 34.50,
        ]);

        $this->assertSame(34.5, $order->effectivePaidAmountEur());
        $this->assertSame(5.5, $order->outstandingAmountEur());
        $this->assertTrue($order->hasOpenAmount());
    }

    public function test_lowered_total_yields_negative_outstanding(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'total_amount_eur' => 30.00,
            'paid_amount_eur' => 34.50,
        ]);

        $this->assertSame(-4.5, $order->outstandingAmountEur());
        $this->assertTrue($order->hasOpenAmount());
    }

    public function test_sub_cent_difference_is_not_open(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'total_amount_eur' => 34.504,
            'paid_amount_eur' => 34.50,
        ]);

        $this->assertFalse($order->hasOpenAmount());
    }

    public function test_editability_by_status(): void
    {
        $editable = [
            ShopOrder::factory()->create(['status' => ShopOrderStatus::PendingPayment, 'payment_method' => 'in_person']),
            ShopOrder::factory()->paid()->create(),
            ShopOrder::factory()->create(['status' => ShopOrderStatus::Fulfilled, 'payment_method' => 'in_person']),
        ];
        foreach ($editable as $order) {
            $this->assertTrue($order->isEditableByAdmin(), $order->status->value.' should be editable');
        }

        $blocked = [
            // Unfinished online checkout: SumUp amount would desync.
            ShopOrder::factory()->create(['status' => ShopOrderStatus::PendingPayment, 'payment_method' => 'online']),
            ShopOrder::factory()->create(['status' => ShopOrderStatus::Cancelled, 'payment_method' => 'online']),
            ShopOrder::factory()->create(['status' => ShopOrderStatus::Failed, 'payment_method' => 'online']),
            ShopOrder::factory()->create(['status' => ShopOrderStatus::Refunded, 'payment_method' => 'online']),
        ];
        foreach ($blocked as $order) {
            $this->assertFalse($order->isEditableByAdmin(), $order->status->value.' must not be editable');
        }
    }
}
