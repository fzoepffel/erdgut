<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\BillingCoverageService;
use App\Support\BillingFulfillmentWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_cell_paid_when_paid_payment_exists_for_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24', 'UTC'));

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $week = Carbon::parse('2026-03-30')->startOfDay();
        $paid = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
            'fulfillment_week_start' => $week->toDateString(),
            'paid_at' => now(),
        ]);

        $service = $this->app->make(BillingCoverageService::class);
        $cell = $service->cellFor($subscription, $week, Carbon::today());

        $this->assertSame('paid', $cell['state']);
        $this->assertSame($paid->id, $cell['payment_id']);
    }

    public function test_cell_pending_manual_when_admin_manual_pending_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24', 'UTC'));

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $week = Carbon::parse('2026-03-30')->startOfDay();
        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'fulfillment_week_start' => $week->toDateString(),
            'charge_initiator' => PaymentChargeInitiator::AdminManual,
            'attempt_number' => 1,
        ]);

        $service = $this->app->make(BillingCoverageService::class);
        $cell = $service->cellFor($subscription, $week, Carbon::today());

        $this->assertSame('pending_manual', $cell['state']);
    }

    public function test_cell_scheduled_when_matches_due_scheduled_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'tok_sched_cell',
            'sumup_customer_id' => 'cust_sched_cell',
            'next_charge_date' => Carbon::parse('2026-03-20'),
        ]);

        $today = Carbon::today();
        $scheduledWeek = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, $today);

        $service = $this->app->make(BillingCoverageService::class);
        $cell = $service->cellFor($subscription, $scheduledWeek, $today);

        $this->assertSame('scheduled', $cell['state']);
    }
}
