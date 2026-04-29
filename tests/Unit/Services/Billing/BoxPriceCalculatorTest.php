<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\BoxPriceCalculator;
use Tests\TestCase;

/**
 * Walks every (size × eggs) combination so changes to one branch can't
 * silently shift other prices. The calculator is database-free, so this is
 * a pure unit test against config.
 *
 * Tracks ticket W2-16 in `docs/walkthrough-2-design-alignment.md`.
 */
class BoxPriceCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('box.included_value_by_size', [
            'small' => 20.0,
            'medium' => 35.0,
            'large' => 42.0,
        ]);
        config()->set('box.eggs_dairy_add_on_eur', 4.5);
    }

    public function test_subscription_per_delivery_returns_size_base_when_eggs_off(): void
    {
        $calc = new BoxPriceCalculator;

        $this->assertSame(20.00, $calc->subscriptionPerDelivery('small'));
        $this->assertSame(35.00, $calc->subscriptionPerDelivery('medium'));
        $this->assertSame(42.00, $calc->subscriptionPerDelivery('large'));
    }

    public function test_subscription_per_delivery_adds_eggs_addon(): void
    {
        $calc = new BoxPriceCalculator;

        $this->assertSame(24.50, $calc->subscriptionPerDelivery('small', true));
        $this->assertSame(39.50, $calc->subscriptionPerDelivery('medium', true));
        $this->assertSame(46.50, $calc->subscriptionPerDelivery('large', true));
    }

    public function test_subscription_line_items_breaks_down_per_charge(): void
    {
        $calc = new BoxPriceCalculator;

        $this->assertSame(
            [['key' => 'box_size', 'size' => 'small', 'amount' => 20.00]],
            $calc->subscriptionLineItems('small'),
        );

        $this->assertSame(
            [
                ['key' => 'box_size', 'size' => 'medium', 'amount' => 35.00],
                ['key' => 'eggs_addon', 'amount' => 4.50],
            ],
            $calc->subscriptionLineItems('medium', true),
        );
    }

    public function test_trial_one_off_uses_size_when_provided(): void
    {
        $calc = new BoxPriceCalculator;

        $this->assertSame(20.00, $calc->trialOneOff('small'));
        $this->assertSame(46.50, $calc->trialOneOff('large', true));
    }

    public function test_trial_one_off_defaults_to_medium_when_size_unknown(): void
    {
        $calc = new BoxPriceCalculator;

        $this->assertSame(35.00, $calc->trialOneOff());
        $this->assertSame(39.50, $calc->trialOneOff(null, true));
    }

    public function test_unknown_size_falls_back_to_medium_via_box_pricing(): void
    {
        // BoxPricing::sizeBaseTtc returns the medium value when the key is unknown.
        $calc = new BoxPriceCalculator;

        $this->assertSame(35.00, $calc->subscriptionPerDelivery('xxl'));
        $this->assertSame(39.50, $calc->subscriptionPerDelivery('xxl', true));
    }
}
