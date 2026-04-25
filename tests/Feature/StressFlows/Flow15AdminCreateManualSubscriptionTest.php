<?php

namespace Tests\Feature\StressFlows;

use App\Enums\BillingMode;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Stress-flow 15 (ERD-342): Admin creates a manual-invoice or manual-cash
 * subscription. Outcome: subscription persists with billing_mode set to
 * the manual variant; subscriptions:charge skips it entirely (no SumUp call).
 */
class Flow15AdminCreateManualSubscriptionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_manual_invoice_subscription_is_excluded_from_sumup_charge_run(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'billing_mode' => BillingMode::ManualInvoice,
            'sumup_token' => null,
            'sumup_customer_id' => null,
            'next_charge_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(BillingMode::ManualInvoice, $subscription->billing_mode);

        // The charge command's filter excludes non-sumup_recurring billing modes,
        // so a dry-run on this fixture should report zero due subscriptions.
        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }
}
