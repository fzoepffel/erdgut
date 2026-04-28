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

class AdminPaymentsExportCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get(route('app.admin.payments.export'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.payments.export'))
            ->assertForbidden();
    }

    public function test_admin_downloads_csv_with_header_and_payments(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'csv-target@example.test']);

        $sub = Subscription::factory()->create(['user_id' => $customer->id]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'amount' => 39.99,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('app.admin.payments.export'));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('payments-', (string) $response->headers->get('Content-Disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('id,type,subscription_id', $body);
        $this->assertStringContainsString('csv-target@example.test', $body);
        $this->assertStringContainsString('subscription', $body);
        $this->assertStringContainsString('39.99', $body);
    }

    public function test_csv_export_respects_kind_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $subCustomer = User::factory()->create(['email' => 'sub-customer@example.test']);
        $orderCustomer = User::factory()->create(['email' => 'order-customer@example.test']);

        $sub = Subscription::factory()->create(['user_id' => $subCustomer->id]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'status' => Payment::STATUS_PAID,
        ]);

        $order = Order::create([
            'user_id' => $orderCustomer->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);
        Payment::query()->create([
            'order_id' => $order->id,
            'charge_initiator' => PaymentChargeInitiator::AdminManual,
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now(),
        ]);

        $body = $this->actingAs($admin)
            ->get(route('app.admin.payments.export', ['kind' => 'order']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('order-customer@example.test', $body);
        $this->assertStringNotContainsString('sub-customer@example.test', $body);
    }
}
