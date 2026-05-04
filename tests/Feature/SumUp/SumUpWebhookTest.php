<?php

namespace Tests\Feature\SumUp;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class SumUpWebhookTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private function signPayload(array $payload, string $secret): string
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $body, $secret);
    }

    private function postSignedWebhook(array $payload, string $secret = 'whsec_test'): TestResponse
    {
        Config::set('sumup.webhook_secret', $secret);

        $signature = $this->signPayload($payload, $secret);

        return $this->withHeaders(['x-payload-signature' => $signature])
            ->postJson(route('sumup.webhook'), $payload);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        Config::set('sumup.webhook_secret', 'whsec_test');

        $this->postJson(route('sumup.webhook'), [
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_123',
        ])->assertUnauthorized();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        Config::set('sumup.webhook_secret', 'whsec_test');

        $this->withHeaders([
            'x-payload-signature' => 'invalid-signature',
        ])->postJson(route('sumup.webhook'), [
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_123',
        ])->assertUnauthorized();
    }

    public function test_webhook_returns_ok_with_valid_signature(): void
    {
        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_123')->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_123',
        ])->assertOk();
    }

    public function test_webhook_allows_when_secret_is_missing(): void
    {
        Config::set('sumup.webhook_secret', '');

        $this->withHeaders([
            'x-payload-signature' => hash_hmac('sha256', '{}', 'different'),
        ])->postJson(route('sumup.webhook'), [])
            ->assertOk();
    }

    public function test_webhook_marks_payment_paid_on_success(): void
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Weekly,
        ]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_paid_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_paid_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_paid_123',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $subscription->refresh();
        $this->assertSame(Carbon::today()->addWeek()->toDateString(), $subscription->next_charge_date?->toDateString());
    }

    public function test_webhook_backfills_sumup_payment_id_from_checkout_transactions(): void
    {
        // Discovered during real-SumUp E2E walk 2026-05-04: charge → reconcile
        // marks Payment paid but never stores transactions[0].id, so admin
        // refund silently degrades to local-only (no sumup_payment_id ⇒ no
        // SumUp refund API call). Webhook handler now backfills it from the
        // checkout response so both webhook + reconcile paths get the id.
        $subscription = Subscription::factory()->create(['plan' => SubscriptionPlan::Weekly]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_with_tx',
            'sumup_payment_id' => null,
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_with_tx')->andReturn((object) [
            'status' => 'PAID',
            'transactions' => [
                (object) ['id' => 'tx-deadbeef-0001', 'status' => 'SUCCESSFUL'],
            ],
        ]);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_with_tx',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('tx-deadbeef-0001', $payment->sumup_payment_id);
    }

    public function test_webhook_does_not_overwrite_existing_sumup_payment_id(): void
    {
        $subscription = Subscription::factory()->create(['plan' => SubscriptionPlan::Weekly]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_existing_tx',
            'sumup_payment_id' => 'pre-existing-tx-id',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_existing_tx')->andReturn((object) [
            'status' => 'PAID',
            'transactions' => [
                (object) ['id' => 'different-tx-id', 'status' => 'SUCCESSFUL'],
            ],
        ]);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_existing_tx',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame('pre-existing-tx-id', $payment->sumup_payment_id);
    }

    public function test_webhook_sets_trial_completed_at_when_first_payment_marked_paid(): void
    {
        $subscription = Subscription::factory()->withPendingTrialBilling()->create([
            'plan' => SubscriptionPlan::Weekly,
        ]);
        $this->assertNull($subscription->trial_completed_at);

        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_trial_done_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_trial_done_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_trial_done_123',
        ])->assertOk();

        $subscription->refresh();
        $this->assertNotNull($subscription->trial_completed_at);
    }

    public function test_webhook_marks_payment_failed_on_failure(): void
    {
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_failed_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_failed_123')->andReturn((object) [
            'status' => 'FAILED',
            'transactions' => [['status' => 'FAILED']],
        ]);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_failed_123',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertNotNull($payment->failed_at);
    }

    public function test_webhook_advances_next_charge_date_weekly(): void
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Weekly,
            'next_charge_date' => Carbon::today(),
        ]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_weekly_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_weekly_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_weekly_123',
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame(Carbon::today()->addWeek()->toDateString(), $subscription->next_charge_date?->toDateString());
    }

    public function test_webhook_advances_next_charge_date_biweekly(): void
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Biweekly,
            'next_charge_date' => Carbon::today(),
        ]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_biweekly_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_biweekly_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_biweekly_123',
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame(Carbon::today()->addWeeks(2)->toDateString(), $subscription->next_charge_date?->toDateString());
    }

    public function test_webhook_does_not_advance_next_charge_date_when_subscription_cancelled(): void
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Cancelled,
            'next_charge_date' => Carbon::today(),
        ]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_cancelled_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_cancelled_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_cancelled_123',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);

        $subscription->refresh();
        $this->assertSame(Carbon::today()->toDateString(), $subscription->next_charge_date?->toDateString());
    }

    public function test_webhook_ignores_checkout_without_payment(): void
    {
        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_setup_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_setup_123',
        ])->assertOk();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_webhook_idempotent_when_payment_already_paid(): void
    {
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
            'sumup_checkout_id' => 'checkout_idempotent_123',
            'paid_at' => now()->subHour(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_idempotent_123')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_idempotent_123',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertTrue($payment->paid_at->isBefore(now()));
    }

    public function test_webhook_acknowledges_unknown_event_type(): void
    {
        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('getCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'UNKNOWN_EVENT',
            'id' => 'checkout_unknown',
        ])->assertOk();
    }

    public function test_webhook_idempotent_duplicate_delivery_does_not_reprocess(): void
    {
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_dup_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('checkout_dup_123')
            ->once()
            ->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $payload = [
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_dup_123',
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertDatabaseCount('webhook_idempotency_keys', 1);
    }

    public function test_webhook_rejects_stale_date_header(): void
    {
        $secret = 'whsec_test';
        Config::set('sumup.webhook_secret', $secret);

        $payload = ['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => 'checkout_stale'];
        $signature = $this->signPayload($payload, $secret);

        $this->withHeaders([
            'x-payload-signature' => $signature,
            'Date' => now()->subMinutes(10)->toRfc7231String(),
        ])->postJson(route('sumup.webhook'), $payload)
            ->assertUnauthorized();
    }

    public function test_webhook_allows_missing_date_header(): void
    {
        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_no_date')->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_no_date',
        ])->assertOk();
    }

    public function test_webhook_marks_payment_failed_on_expired_checkout(): void
    {
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_expired_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_expired_123')->andReturn((object) [
            'status' => 'EXPIRED',
            'transactions' => [],
        ]);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_expired_123',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertNotNull($payment->failed_at);
    }

    public function test_webhook_returns_ok_when_get_checkout_throws(): void
    {
        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_throws_123',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('checkout_throws_123')
            ->andThrow(new \RuntimeException('SumUp API unreachable'));
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_throws_123',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_webhook_sends_payment_success_notification(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Weekly,
        ]);
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_notif_paid',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_notif_paid')->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_notif_paid',
        ])->assertOk();

        Notification::assertSentTo($subscription->user, PaymentSuccessNotification::class);
    }

    public function test_webhook_sends_payment_failed_notification(): void
    {
        Notification::fake();

        $subscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_notif_failed',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')->with('checkout_notif_failed')->andReturn((object) [
            'status' => 'FAILED',
            'transactions' => [['status' => 'DECLINED']],
        ]);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->postSignedWebhook([
            'event_type' => 'CHECKOUT_STATUS_CHANGED',
            'id' => 'checkout_notif_failed',
        ])->assertOk();

        Notification::assertSentTo($subscription->user, PaymentFailedNotification::class);
    }
}
