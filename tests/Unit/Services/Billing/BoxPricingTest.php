<?php

namespace Tests\Unit\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BoxPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('box.included_value_by_size', [
            'small' => 20.0,
            'medium' => 35.0,
            'large' => 42.0,
        ]);
        config()->set('box.trial_price_ttc', 35.0);
        config()->set('box.eggs_dairy_add_on_eur', 4.5);
        config()->set('box.trial_billing_enabled', true);
    }

    public function test_size_base_ttc_returns_configured_value_per_size(): void
    {
        $this->assertSame(20.0, BoxPricing::sizeBaseTtc('small'));
        $this->assertSame(35.0, BoxPricing::sizeBaseTtc('medium'));
        $this->assertSame(42.0, BoxPricing::sizeBaseTtc('large'));
    }

    public function test_size_base_ttc_falls_back_to_medium_for_unknown_size(): void
    {
        $this->assertSame(35.0, BoxPricing::sizeBaseTtc('humongous'));
    }

    public function test_trial_order_total_uses_size_based_pricing(): void
    {
        $this->assertSame(20.0, BoxPricing::trialOrderTotal('small'));
        $this->assertSame(42.0, BoxPricing::trialOrderTotal('large'));
    }

    public function test_trial_order_total_adds_eggs_dairy_when_enabled(): void
    {
        $this->assertSame(24.5, BoxPricing::trialOrderTotal('small', true));
        $this->assertSame(46.5, BoxPricing::trialOrderTotal('large', true));
    }

    public function test_subscription_recurring_total_uses_flat_trial_price_while_pending(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'large',
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => null,
            'preferences' => ['eggs_dairy_add_on' => false],
        ]);

        $this->assertSame(35.0, BoxPricing::subscriptionRecurringTotal($subscription));
    }

    public function test_subscription_recurring_total_adds_eggs_while_trial_pending(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'small',
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => null,
            'preferences' => ['eggs_dairy_add_on' => true],
        ]);

        $this->assertSame(39.5, BoxPricing::subscriptionRecurringTotal($subscription));
    }

    public function test_subscription_recurring_total_uses_size_price_after_trial(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'large',
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
            'preferences' => ['eggs_dairy_add_on' => false],
        ]);

        $this->assertSame(42.0, BoxPricing::subscriptionRecurringTotal($subscription));
    }

    public function test_subscription_recurring_total_adds_eggs_after_trial(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'small',
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
            'preferences' => ['eggs_dairy_add_on' => true],
        ]);

        $this->assertSame(24.5, BoxPricing::subscriptionRecurringTotal($subscription));
    }
}
