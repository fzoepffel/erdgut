<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * W2-16 item 2: every subscription charge is `size-base + eggs-addon`.
 * The trial-flat-first-charge override is gone, so `trial_completed_at`
 * no longer affects the charged amount.
 */
class SubscriptionBillingAmountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expected_charge_uses_size_base_even_when_trial_not_completed(): void
    {
        Config::set('box.trial_billing_enabled', true); // legacy flag — has no effect on pricing
        Config::set('box.trial_price_ttc', 35);
        Config::set('box.included_value_by_size.small', 20.0);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'small',
            'trial_completed_at' => null,
        ]);

        $this->assertSame(20.0, $subscription->expectedChargeAmount());
        $this->assertSame(20.0, $subscription->includedValueForBudget());
    }

    public function test_expected_charge_uses_size_price_after_trial(): void
    {
        Config::set('box.included_value_by_size.small', 20.0);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'small',
        ]);

        $this->assertSame(20.0, $subscription->expectedChargeAmount());
    }

    public function test_expected_charge_adds_eggs_dairy_when_enabled(): void
    {
        Config::set('box.included_value_by_size.medium', 35.0);
        Config::set('box.eggs_dairy_add_on_eur', 4.5);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'medium',
            'preferences' => [
                'veg_only' => false,
                'meat_add_on' => false,
                'eggs_dairy_add_on' => true,
            ],
        ]);

        $this->assertSame(39.5, $subscription->expectedChargeAmount());
    }

    public function test_eggs_dairy_addon_applies_even_during_first_billing_period(): void
    {
        // Regression-guard: an admin-walkthrough surfaced that the displayed
        // first-charge price ignored the eggs add-on. After W2-16 item 2,
        // every charge is size + eggs, regardless of trial state.
        Config::set('box.included_value_by_size.small', 20.0);
        Config::set('box.eggs_dairy_add_on_eur', 4.5);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'small',
            'trial_completed_at' => null, // still in first billing period
            'preferences' => [
                'veg_only' => false,
                'meat_add_on' => false,
                'eggs_dairy_add_on' => true,
            ],
        ]);

        $this->assertSame(24.5, $subscription->expectedChargeAmount());
    }

    public function test_nominal_included_value_ttc_uses_size_config_regardless_of_trial(): void
    {
        Config::set('box.included_value_by_size.large', 42.0);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => 'large',
            'trial_completed_at' => null,
        ]);

        $this->assertSame(42.0, $subscription->nominalIncludedValueTtc());
        $this->assertSame(42.0, $subscription->includedValueForBudget());
    }
}
