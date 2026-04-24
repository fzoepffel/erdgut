<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Models\AuditEvent;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMarkPaymentPaidExternallyTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_guest_cannot_mark_payment_paid_externally(): void
    {
        $payment = $this->pendingPayment(User::factory()->create());

        $this->post(route('app.admin.payments.mark-paid-externally', $payment), [
            'external_reference' => 'BANK-123',
        ])->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_cannot_mark_payment_paid_externally(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $payment = $this->pendingPayment(User::factory()->create());

        $this->actingAs($user)
            ->post(route('app.admin.payments.mark-paid-externally', $payment), [
                'external_reference' => 'BANK-123',
            ])->assertForbidden();
    }

    public function test_admin_can_mark_pending_payment_paid_externally(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->pendingPayment(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('app.admin.payments.index'))
            ->post(route('app.admin.payments.mark-paid-externally', $payment), [
                'external_reference' => 'SEPA-2026-0042',
                'reason' => 'Customer paid by bank transfer.',
            ])
            ->assertRedirect(route('app.admin.payments.index'))
            ->assertSessionHas('status', 'admin-payment-marked-paid-externally');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('SEPA-2026-0042', $payment->external_reference);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->manual_marked_at);
        $this->assertSame($admin->id, $payment->manual_marked_by_user_id);

        $event = AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->where('action', 'manual_paid')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame(Payment::STATUS_PENDING, $event->from_state);
        $this->assertSame(Payment::STATUS_PAID, $event->to_state);
        $this->assertSame('Customer paid by bank transfer.', $event->reason);
        $this->assertSame('SEPA-2026-0042', $event->metadata['external_reference'] ?? null);
    }

    public function test_external_reference_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->pendingPayment(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('app.admin.payments.index'))
            ->post(route('app.admin.payments.mark-paid-externally', $payment), [])
            ->assertSessionHasErrors('external_reference');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_already_paid_payment_is_not_re_marked(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create(['user_id' => User::factory()->create()->id]);

        $payment = Payment::query()->create([
            'subscription_id' => $subscription->id,
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.payments.index'))
            ->post(route('app.admin.payments.mark-paid-externally', $payment), [
                'external_reference' => 'BANK-999',
            ])
            ->assertSessionHas('status', 'admin-payment-already-paid');

        $payment->refresh();
        $this->assertNull($payment->external_reference);
        $this->assertNull($payment->manual_marked_at);

        $this->assertFalse(AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->exists());
    }
}
