<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\PaymentChargeInitiator;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_payments_by_kind_order_and_sees_order_user(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'trial-customer@example.test']);

        $order = Order::create([
            'user_id' => $customer->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);

        $orderPayment = Payment::query()->create([
            'order_id' => $order->id,
            'charge_initiator' => PaymentChargeInitiator::AdminManual,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.payments.index', ['kind' => 'order']))
            ->assertOk()
            ->assertSee($customer->email)
            ->assertSee('#'.$order->id)
            ->assertSee((string) $orderPayment->id);
    }

    public function test_admin_can_filter_payments_by_kind_subscription_and_sees_subscription_user(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'subscriber@example.test']);

        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
        ]);

        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.payments.index', ['kind' => 'subscription']))
            ->assertOk()
            ->assertSee($customer->email)
            ->assertSee('#'.$subscription->id)
            ->assertSee((string) $payment->id);
    }
}
