<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentShowTest extends TestCase
{
    use RefreshDatabase;

    private function paidPayment(User $customer): Payment
    {
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        return Payment::query()->create([
            'subscription_id' => $subscription->id,
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now()->subDay(),
        ]);
    }

    public function test_guest_cannot_view_payment_detail(): void
    {
        $payment = $this->paidPayment(User::factory()->create());

        $this->get(route('app.admin.payments.show', $payment))
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_cannot_view_payment_detail(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $payment = $this->paidPayment(User::factory()->create());

        $this->actingAs($user)
            ->get(route('app.admin.payments.show', $payment))
            ->assertForbidden();
    }

    public function test_admin_can_view_payment_detail_with_audit_events(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->paidPayment(User::factory()->create());

        // Simulate a couple of historical audit entries so the detail page has rows to render.
        AuditLogger::log(
            entityType: 'payment',
            entityId: $payment->id,
            action: 'force_charge',
            fromState: null,
            toState: Payment::STATUS_PENDING,
            reason: 'Admin-initiated retry.',
            metadata: ['initiator' => 'admin'],
            actorUserId: $admin->id,
        );

        AuditLogger::log(
            entityType: 'payment',
            entityId: $payment->id,
            action: 'manual_paid',
            fromState: Payment::STATUS_PENDING,
            toState: Payment::STATUS_PAID,
            reason: 'Customer paid by bank transfer.',
            metadata: [
                'external_reference' => 'SEPA-2026-0001',
                'amount' => '35',
                'currency' => 'EUR',
            ],
            actorUserId: $admin->id,
        );

        $response = $this->actingAs($admin)
            ->get(route('app.admin.payments.show', $payment))
            ->assertOk();

        $response->assertSee('force_charge', false);
        $response->assertSee('manual_paid', false);
        $response->assertSee('Customer paid by bank transfer.', false);
        // Refund form should be rendered for paid payments.
        $response->assertSee('admin-payment-refund-submit', false);
    }

    public function test_refund_form_hidden_when_payment_not_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create(['user_id' => User::factory()->create()->id]);
        $payment = Payment::query()->create([
            'subscription_id' => $subscription->id,
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.payments.show', $payment))
            ->assertOk();

        $response->assertDontSee('admin-payment-refund-submit', false);
    }
}
