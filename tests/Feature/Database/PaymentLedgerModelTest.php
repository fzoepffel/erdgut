<?php

namespace Tests\Feature\Database;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentLedgerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_attempt_persists_with_status_attempt_and_provider_refs(): void
    {
        $subscription = Subscription::factory()->create();

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => 34.90,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
            'sumup_checkout_id' => 'checkout-1',
            'sumup_payment_id' => 'payment-1',
            'provider_payload' => ['provider' => 'sumup'],
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'subscription_id' => $subscription->id,
            'amount' => 34.9,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
            'sumup_checkout_id' => 'checkout-1',
            'sumup_payment_id' => 'payment-1',
        ]);
    }

    public function test_payment_status_transitions_persist_timestamps(): void
    {
        $payment = Payment::factory()->create([
            'status' => Payment::STATUS_PENDING,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        $payment->markFailed('insufficient_funds');
        $payment->refresh();

        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertNotNull($payment->failed_at);
        $this->assertSame('insufficient_funds', $payment->failure_reason);
        $this->assertNull($payment->paid_at);

        $payment->markPaid();
        $payment->refresh();

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNull($payment->failed_at);
        $this->assertNull($payment->failure_reason);
    }

    public function test_payment_attempt_history_is_queryable_for_subscription(): void
    {
        $subscription = Subscription::factory()->create();

        $firstAttempt = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'attempt_number' => 1,
            'status' => Payment::STATUS_FAILED,
            'created_at' => now()->subMinute(),
        ]);

        $secondAttempt = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'attempt_number' => 2,
            'status' => Payment::STATUS_PAID,
            'created_at' => now(),
        ]);

        $history = $subscription->payments()
            ->orderBy('created_at')
            ->pluck('id')
            ->all();

        $this->assertSame([$firstAttempt->id, $secondAttempt->id], $history);
    }

    public function test_relationship_contracts_are_exposed(): void
    {
        $subscription = new Subscription;
        $payment = new Payment;

        $this->assertInstanceOf(HasMany::class, $subscription->payments());
        $this->assertInstanceOf(BelongsTo::class, $payment->subscription());
    }

    public function test_duplicate_attempt_number_for_same_subscription_is_rejected(): void
    {
        $subscription = Subscription::factory()->create();

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'attempt_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'attempt_number' => 1,
        ]);
    }
}
