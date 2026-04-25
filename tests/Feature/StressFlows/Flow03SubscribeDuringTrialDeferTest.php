<?php

namespace Tests\Feature\StressFlows;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 3 (ERD-342): A user with a pending trial order can subscribe;
 * the subscription's first charge defers past the trial delivery date.
 * The audit flagged this as broken pre-Phase 6 — this test protects the fix.
 */
class Flow03SubscribeDuringTrialDeferTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_and_subscription_coexist_with_deferred_billing(): void
    {
        $user = User::factory()->create();

        $trialOrder = Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'box_size' => 'medium',
            'preferences' => [],
            'allergies' => [],
            'amount' => 35.00,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid,
            'is_test' => false,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => null,
            'next_charge_date' => now()->addWeeks(2)->toDateString(),
        ]);

        // Both exist on the same user without conflict.
        $this->assertSame(OrderStatus::Paid->value, $trialOrder->status->value);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);

        // Next charge is deferred past "now" — the subscription won't be
        // picked up by subscriptions:charge on today's run.
        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }
}
