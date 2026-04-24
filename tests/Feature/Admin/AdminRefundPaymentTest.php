<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Models\AuditEvent;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminRefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function paidPayment(User $customer, array $overrides = []): Payment
    {
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        return Payment::query()->create(array_merge([
            'subscription_id' => $subscription->id,
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now()->subDay(),
        ], $overrides));
    }

    private function pendingPayment(User $customer): Payment
    {
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        return Payment::query()->create([
            'subscription_id' => $subscription->id,
            'charge_initiator' => PaymentChargeInitiator::AdminManual,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
        ]);
    }

    public function test_guest_cannot_refund_payment(): void
    {
        $payment = $this->paidPayment(User::factory()->create());

        $this->post(route('app.admin.payments.refund', $payment), [
            'refund_amount' => 10,
        ])->assertRedirect(route('auth.login'));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_non_admin_cannot_refund_payment(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $payment = $this->paidPayment(User::factory()->create());

        $this->actingAs($user)
            ->post(route('app.admin.payments.refund', $payment), [
                'refund_amount' => 10,
            ])->assertForbidden();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_admin_can_refund_paid_payment(): void
    {
        config(['payments.refund_api_enabled' => false]);

        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('app.admin.payments.show', $payment))
            ->post(route('app.admin.payments.refund', $payment), [
                'refund_amount' => 20,
                'refund_reason' => 'Damaged box.',
                'refund_reference' => 'MANUAL-REF-1',
            ])
            ->assertRedirect(route('app.admin.payments.show', $payment))
            ->assertSessionHas('status', 'admin-payment-refunded');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertEquals(20.00, (float) $payment->refund_amount);
        $this->assertSame('Damaged box.', $payment->refund_reason);
        $this->assertSame('MANUAL-REF-1', $payment->refund_reference);
        $this->assertSame($admin->id, $payment->refunded_by_user_id);
        $this->assertNotNull($payment->refunded_at);

        $event = AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->where('action', 'refund')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame(Payment::STATUS_PAID, $event->from_state);
        $this->assertSame(Payment::STATUS_REFUNDED, $event->to_state);
        $this->assertSame('Damaged box.', $event->reason);
        $this->assertSame('20', $event->metadata['amount'] ?? null);
        $this->assertSame('EUR', $event->metadata['currency'] ?? null);
        $this->assertSame('MANUAL-REF-1', $event->metadata['refund_reference'] ?? null);
        // Provider call not triggered when the flag is off — metadata omits provider_call.
        $this->assertArrayNotHasKey('provider_call', $event->metadata);
    }

    public function test_refund_defaults_to_full_payment_amount_when_amount_omitted(): void
    {
        config(['payments.refund_api_enabled' => false]);

        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create());

        $this->actingAs($admin)
            ->post(route('app.admin.payments.refund', $payment), [])
            ->assertSessionHas('status', 'admin-payment-refunded');

        $payment->refresh();
        $this->assertEquals(35.00, (float) $payment->refund_amount);
    }

    public function test_refund_rejected_when_payment_not_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->pendingPayment(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('app.admin.payments.show', $payment))
            ->post(route('app.admin.payments.refund', $payment), [
                'refund_amount' => 10,
            ])
            ->assertRedirect(route('app.admin.payments.show', $payment))
            ->assertSessionHas('status', 'admin-payment-refund-not-allowed');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertNull($payment->refunded_at);

        $this->assertFalse(AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->where('action', 'refund')
            ->exists());
    }

    public function test_refund_amount_must_be_positive(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('app.admin.payments.show', $payment))
            ->post(route('app.admin.payments.refund', $payment), [
                'refund_amount' => 0,
            ])
            ->assertSessionHasErrors('refund_amount');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_refund_amount_cap_rejects_absurd_values(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('app.admin.payments.show', $payment))
            ->post(route('app.admin.payments.refund', $payment), [
                'refund_amount' => 100001,
            ])
            ->assertSessionHasErrors('refund_amount');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_refund_skips_provider_call_when_flag_off(): void
    {
        config(['payments.refund_api_enabled' => false]);

        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create(), [
            'sumup_payment_id' => 'sumup_txn_123',
        ]);

        $this->mock(SumUpService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('refund');
        });

        $this->actingAs($admin)
            ->post(route('app.admin.payments.refund', $payment), [])
            ->assertSessionHas('status', 'admin-payment-refunded');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
    }

    public function test_refund_calls_provider_when_flag_on_and_records_ok_in_audit(): void
    {
        config(['payments.refund_api_enabled' => true]);

        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create(), [
            'sumup_payment_id' => 'sumup_txn_abc',
        ]);

        $this->mock(SumUpService::class, function (MockInterface $mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->with('sumup_txn_abc', Mockery::type('float'))
                ->andReturn(true);
        });

        $this->actingAs($admin)
            ->post(route('app.admin.payments.refund', $payment), [
                'refund_amount' => 12.5,
            ])
            ->assertSessionHas('status', 'admin-payment-refunded');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);

        $event = AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->where('action', 'refund')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('ok', $event->metadata['provider_call'] ?? null);
    }

    public function test_refund_records_ledger_and_surfaces_warning_when_provider_fails(): void
    {
        config(['payments.refund_api_enabled' => true]);

        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create(), [
            'sumup_payment_id' => 'sumup_txn_fail',
        ]);

        $this->mock(SumUpService::class, function (MockInterface $mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->andThrow(new \RuntimeException('provider blew up'));
        });

        $this->actingAs($admin)
            ->post(route('app.admin.payments.refund', $payment), [])
            ->assertSessionHas('status', 'admin-payment-refund-recorded-provider-failed');

        $payment->refresh();
        // Ledger must still reflect the refund so books don't drift.
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertEquals(35.00, (float) $payment->refund_amount);

        $event = AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->where('action', 'refund')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('failed', $event->metadata['provider_call'] ?? null);
    }

    public function test_refund_with_missing_provider_id_skips_api_even_when_flag_on(): void
    {
        config(['payments.refund_api_enabled' => true]);

        $admin = User::factory()->admin()->create();
        // No sumup_payment_id set — manual-only payment, e.g. one previously marked paid externally.
        $payment = $this->paidPayment(User::factory()->create());

        $this->mock(SumUpService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('refund');
        });

        $this->actingAs($admin)
            ->post(route('app.admin.payments.refund', $payment), [])
            ->assertSessionHas('status', 'admin-payment-refunded');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
    }
}
