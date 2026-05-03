<?php

namespace Tests\Feature\Audit;

use App\Enums\SubscriptionStatus;
use App\Models\AuditEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionLifecycleService;
use App\Support\SubscriptionSkipService;
use App\Support\SubscriptionUpdateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditLoggerBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-04-01');
        CarbonImmutable::setTestNow('2026-04-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cancelling_a_subscription_writes_a_single_audit_row_with_correct_payload(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionLifecycleService::class)
            ->cancelNow($subscription, 'No longer needed', $user->id);

        // Filter to subscription rows only — the LogSentMail listener (ERD-350)
        // also writes mail.sent rows for any cancellation email queued, which
        // is correct behaviour but unrelated to what this test asserts.
        $this->assertSame(1, AuditEvent::query()->where('entity_type', 'subscription')->count());
        $event = AuditEvent::query()->where('entity_type', 'subscription')->first();
        $this->assertSame('subscription', $event->entity_type);
        $this->assertSame($subscription->id, $event->entity_id);
        $this->assertSame('cancelled', $event->action);
        $this->assertSame(SubscriptionStatus::Active->value, $event->from_state);
        $this->assertSame(SubscriptionStatus::Cancelled->value, $event->to_state);
        $this->assertSame('No longer needed', $event->reason);
        $this->assertSame($user->id, $event->actor_user_id);
        $this->assertSame($user->id, $event->metadata['subscription_user_id']);
    }

    public function test_pausing_a_subscription_writes_audit_row_with_pause_window(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionLifecycleService::class)
            ->schedulePause($subscription, '2026-04-08', '2026-04-22', $subscription->user_id);

        $event = AuditEvent::where('action', 'paused')->first();
        $this->assertNotNull($event);
        $this->assertSame(SubscriptionStatus::Active->value, $event->from_state);
        $this->assertSame('2026-04-08', $event->metadata['pause_from']);
        $this->assertSame('2026-04-22', $event->metadata['pause_until']);
    }

    public function test_resuming_a_paused_subscription_writes_audit_row_with_next_charge_date(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Paused,
            'pause_from' => '2026-03-30',
            'pause_until' => '2026-04-10',
        ]);

        app(SubscriptionLifecycleService::class)
            ->resumeNow($subscription, $subscription->user_id);

        $event = AuditEvent::where('action', 'resumed')->first();
        $this->assertNotNull($event);
        $this->assertSame(SubscriptionStatus::Paused->value, $event->from_state);
        $this->assertSame(SubscriptionStatus::Active->value, $event->to_state);
        $this->assertArrayHasKey('next_charge_date', $event->metadata);
    }

    public function test_updating_a_subscription_writes_plan_change_audit_row(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'box_size' => 'small',
        ]);

        app(SubscriptionUpdateService::class)
            ->update($subscription, ['box_size' => 'large'], $subscription->user_id);

        $event = AuditEvent::where('action', 'plan_change')->first();
        $this->assertNotNull($event);
        $this->assertSame('subscription', $event->entity_type);
        $this->assertContains('box_size', $event->metadata['changed_fields']);
    }

    public function test_updating_skip_weeks_writes_skip_audit_row(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionSkipService::class)
            ->syncUserSkipWeeks($subscription, ['2026-04-13'], $subscription->user_id);

        $event = AuditEvent::where('action', 'skip')->first();
        $this->assertNotNull($event);
        $this->assertSame('subscription', $event->entity_type);
        $this->assertSame(['2026-04-13'], $event->metadata['skip_weeks']);
    }
}
