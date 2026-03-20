<?php

namespace Tests\Feature\SumUp;

use App\Models\Payment;
use App\Models\Subscription;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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
        $subscription = Subscription::factory()->create();
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
}
