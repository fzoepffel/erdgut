<?php

namespace Tests\Unit\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\AdminDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_reflects_users_subscriptions_payments_and_upcoming_billable(): void
    {
        $this->travelTo(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create(['email_verified_at' => now()]);
        $userB = User::factory()->create(['email_verified_at' => now()]);
        $userUnverified = User::factory()->unverified()->create();

        Subscription::factory()->for($userA)->create(['status' => SubscriptionStatus::Active]);
        Subscription::factory()->for($userA)->create(['status' => SubscriptionStatus::Paused]);
        Subscription::factory()->for($userB)->create(['status' => SubscriptionStatus::Cancelled]);

        $billable = Subscription::factory()->for($admin)->create([
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_billable_1',
            'sumup_customer_id' => 'cust_1',
            'box_size' => 'small',
            'next_charge_date' => '2025-06-18',
        ]);

        Subscription::factory()->for($userB)->create([
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_paused_window',
            'sumup_customer_id' => 'cust_2',
            'box_size' => 'medium',
            'next_charge_date' => '2025-06-20',
            'pause_from' => '2025-06-10',
            'pause_until' => '2025-06-25',
        ]);

        $pendingSub = Subscription::factory()->for($userUnverified)->create([
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_pending_pay',
            'sumup_customer_id' => 'cust_3',
        ]);

        Payment::factory()->for($billable)->create([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
        ]);

        Payment::factory()->for($pendingSub)->create([
            'status' => Payment::STATUS_PENDING,
        ]);

        Producer::factory()->count(2)->create();
        $catalogProducer = Producer::factory()->create();
        Product::factory()->count(2)->for($catalogProducer)->create(['is_published' => true]);
        Product::factory()->for($catalogProducer)->create(['is_published' => false]);

        $metrics = app(AdminDashboardMetrics::class)->build(Carbon::now());

        $this->assertSame(4, $metrics->usersTotal);
        $this->assertSame(3, $metrics->usersVerified);
        $this->assertSame(4, $metrics->usersSignedUpLast7Days);
        $this->assertSame(4, $metrics->subscriptionsActive);
        $this->assertSame(1, $metrics->subscriptionsPaused);
        $this->assertSame(1, $metrics->subscriptionsCancelled);
        $this->assertSame(3, $metrics->producersTotal);
        $this->assertSame(2, $metrics->productsPublished);
        $this->assertSame(1, $metrics->productsDraft);
        $this->assertSame(1, $metrics->paymentsFailedLast7Days);
        $this->assertSame(1, $metrics->paymentsPending);
        $this->assertSame(1, $metrics->upcomingBillableCharges7dCount);
        $this->assertSame(1, $metrics->upcomingBillableCharges30dCount);
        $this->assertSame(25.0, $metrics->upcomingBillableAmount7d);
        $this->assertSame(25.0, $metrics->upcomingBillableAmount30d);
    }

    public function test_upcoming_excludes_active_subscription_without_token(): void
    {
        $this->travelTo(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $user = User::factory()->create();
        Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => null,
            'next_charge_date' => '2025-06-16',
        ]);

        $metrics = app(AdminDashboardMetrics::class)->build(Carbon::now());

        $this->assertSame(0, $metrics->upcomingBillableCharges7dCount);
        $this->assertSame(0.0, $metrics->upcomingBillableAmount7d);
    }
}
