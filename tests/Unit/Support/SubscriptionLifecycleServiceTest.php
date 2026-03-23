<?php

namespace Tests\Unit\Support;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\SubscriptionLifecycleService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionLifecycleService::class);
        Carbon::setTestNow('2026-04-01');
        CarbonImmutable::setTestNow('2026-04-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_schedule_pause_sets_dates_and_keeps_active_when_future(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $result = $this->service->schedulePause($subscription, '2026-04-08', '2026-04-22', $subscription->user_id);

        $this->assertSame(SubscriptionStatus::Active, $result->status);
        $this->assertSame('2026-04-08', $result->pause_from->toDateString());
        $this->assertSame('2026-04-22', $result->pause_until->toDateString());
    }

    public function test_schedule_pause_sets_paused_when_today_is_within_window(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $result = $this->service->schedulePause($subscription, '2026-04-01', '2026-04-15', $subscription->user_id);

        $this->assertSame(SubscriptionStatus::Paused, $result->status);
        $this->assertSame('2026-04-01', $result->pause_from->toDateString());
        $this->assertSame('2026-04-15', $result->pause_until->toDateString());
    }

    public function test_schedule_pause_rejects_cancelled_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cancelled subscriptions cannot be paused.');

        $this->service->schedulePause($subscription, '2026-04-08', '2026-04-22', $subscription->user_id);
    }

    public function test_schedule_pause_rejects_from_after_until(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('pause_from must be before or equal to pause_until.');

        $this->service->schedulePause($subscription, '2026-04-22', '2026-04-08', $subscription->user_id);
    }

    public function test_schedule_pause_rejects_from_in_the_past(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('pause_from must be today or in the future.');

        $this->service->schedulePause($subscription, '2026-03-25', '2026-04-08', $subscription->user_id);
    }

    public function test_schedule_pause_rejects_duration_exceeding_12_weeks(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pause duration cannot exceed 12 weeks.');

        $this->service->schedulePause($subscription, '2026-04-01', '2026-07-15', $subscription->user_id);
    }

    public function test_resume_now_clears_pause_and_sets_active(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Paused,
            'plan' => SubscriptionPlan::Weekly,
            'pause_from' => '2026-03-25',
            'pause_until' => '2026-04-08',
        ]);

        $result = $this->service->resumeNow($subscription, $subscription->user_id);

        $this->assertSame(SubscriptionStatus::Active, $result->status);
        $this->assertNull($result->pause_from);
        $this->assertNull($result->pause_until);
        $this->assertSame('2026-04-08', $result->next_charge_date->toDateString());
    }

    public function test_resume_now_rejects_cancelled_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only paused subscriptions can be resumed.');

        $this->service->resumeNow($subscription, $subscription->user_id);
    }

    public function test_resume_now_rejects_active_subscription(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only paused subscriptions can be resumed.');

        $this->service->resumeNow($subscription, $subscription->user_id);
    }

    public function test_cancel_now_sets_cancelled_with_reason(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'pause_from' => '2026-04-08',
            'pause_until' => '2026-04-22',
        ]);

        $result = $this->service->cancelNow($subscription, 'Too expensive', $subscription->user_id);

        $this->assertSame(SubscriptionStatus::Cancelled, $result->status);
        $this->assertNotNull($result->cancelled_at);
        $this->assertSame('Too expensive', $result->cancellation_reason);
        $this->assertNull($result->pause_from);
        $this->assertNull($result->pause_until);
    }

    public function test_cancel_now_rejects_already_cancelled(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Subscription is already cancelled.');

        $this->service->cancelNow($subscription, 'Again', $subscription->user_id);
    }

    public function test_compute_next_charge_date_weekly(): void
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $this->assertSame('2026-04-08', $this->service->computeNextChargeDate($subscription));
    }

    public function test_compute_next_charge_date_biweekly(): void
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Biweekly,
        ]);

        $this->assertSame('2026-04-15', $this->service->computeNextChargeDate($subscription));
    }
}
