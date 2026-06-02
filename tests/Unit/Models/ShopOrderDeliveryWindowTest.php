<?php

namespace Tests\Unit\Models;

use App\Models\ShopOrder;
use PHPUnit\Framework\TestCase;

class ShopOrderDeliveryWindowTest extends TestCase
{
    public function test_has_window_is_false_when_either_end_missing(): void
    {
        $this->assertFalse((new ShopOrder(['delivery_window_from' => null, 'delivery_window_to' => null]))->hasDeliveryWindow());
        $this->assertFalse((new ShopOrder(['delivery_window_from' => '14:00', 'delivery_window_to' => null]))->hasDeliveryWindow());
        $this->assertFalse((new ShopOrder(['delivery_window_from' => null, 'delivery_window_to' => '16:00']))->hasDeliveryWindow());
    }

    public function test_has_window_is_true_when_both_set(): void
    {
        $this->assertTrue((new ShopOrder(['delivery_window_from' => '14:00', 'delivery_window_to' => '16:00']))->hasDeliveryWindow());
    }

    public function test_label_is_null_without_a_window(): void
    {
        $this->assertNull((new ShopOrder(['delivery_window_from' => null, 'delivery_window_to' => null]))->deliveryWindowLabel());
    }

    public function test_label_trims_seconds_to_hh_mm(): void
    {
        $order = new ShopOrder(['delivery_window_from' => '14:00:00', 'delivery_window_to' => '16:30:00']);
        $this->assertSame('14:00 – 16:30', $order->deliveryWindowLabel());
    }
}
