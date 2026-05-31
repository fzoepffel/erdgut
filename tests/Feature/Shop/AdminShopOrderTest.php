<?php

namespace Tests\Feature\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\ShopOrder;
use App\Models\User;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\Feature\Spargel\SpargelAdminTest;
use Tests\TestCase;

/**
 * Coverage for the admin shop-orders surface inside the `app.admin.*`
 * group: list/filter, detail, mark-paid, mark-completed, refund — each
 * writing to the audit log. Mirrors {@see SpargelAdminTest}
 * generalized to multi-product orders.
 */
class AdminShopOrderTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.admin.shop-orders.index'))
            ->assertRedirect();
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.shop-orders.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_orders_with_customer_details(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'status' => ShopOrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee('Anna Mustermann')
            ->assertSee('data-testid="shop-admin-row-'.$order->id.'"', false);
    }

    public function test_index_filters_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $paid = ShopOrder::factory()->paid()->create(['name' => 'Paid Customer']);
        $pending = ShopOrder::factory()->create([
            'name' => 'Pending Customer',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['status' => ShopOrderStatus::Paid->value]))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-row-'.$paid->id.'"', false)
            ->assertDontSee('data-testid="shop-admin-row-'.$pending->id.'"', false);
    }

    public function test_admin_can_open_order_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->paid()->create(['name' => 'Detail Customer']);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.show', $order))
            ->assertOk()
            ->assertSee('Detail Customer');
    }

    public function test_mark_paid_flips_pending_order_and_audits(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.mark-paid', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order))
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->marked_paid_by_admin_at);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'mark_paid',
            'from_state' => 'pending_payment',
            'to_state' => 'paid',
        ]);
    }

    public function test_mark_completed_sets_fulfilled(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->paid()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.mark-completed', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Fulfilled, $order->status);
        $this->assertNotNull($order->fulfilled_at);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'mark_completed',
            'to_state' => 'fulfilled',
        ]);
    }

    public function test_mark_completed_on_pending_in_person_marks_paid_and_done(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.mark-completed', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Fulfilled, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->marked_paid_by_admin_at);
        $this->assertNotNull($order->fulfilled_at);
    }

    public function test_admin_can_refund_a_paid_order_via_sumup(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::Paid,
            'total_amount_eur' => 12.50,
            'paid_at' => now()->subMinutes(20),
            'sumup_payment_id' => 'tx-real-123',
            'payment_method' => 'online',
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('refund')
            ->once()
            ->with('tx-real-123', Mockery::on(fn ($v) => abs((float) $v - 12.50) < 0.001))
            ->andReturn(true);
        $this->app->instance(SumUpService::class, $sumUp);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.refund', $order), ['refund_reason' => 'customer changed plans'])
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Refunded, $order->status);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame('customer changed plans', $order->refund_reason);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'refund',
            'from_state' => 'paid',
            'to_state' => 'refunded',
        ]);
    }

    public function test_failed_sumup_refund_keeps_status_paid_and_records_attempt(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::Paid,
            'total_amount_eur' => 8.00,
            'sumup_payment_id' => 'tx-will-fail',
            'payment_method' => 'online',
            'refund_attempts' => 0,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('refund')
            ->once()
            ->andThrow(new \RuntimeException('NOT_ENOUGH_BALANCE'));
        $this->app->instance(SumUpService::class, $sumUp);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.refund', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertNull($order->refunded_at);
        $this->assertSame(1, (int) $order->refund_attempts);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $order->id,
            'action' => 'refund_attempt_failed',
            'from_state' => 'paid',
            'to_state' => 'paid',
        ]);
    }

    public function test_refund_rejects_non_paid_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.refund', $order))
            ->assertRedirect(route('app.admin.shop-orders.show', $order));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->refunded_at);
    }

    public function test_non_admin_cannot_mark_paid(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $order = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $this->actingAs($user)
            ->post(route('app.admin.shop-orders.mark-paid', $order))
            ->assertForbidden();

        $this->assertSame(ShopOrderStatus::PendingPayment, $order->fresh()->status);
    }
}
