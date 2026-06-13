<?php

namespace Tests\Feature\Shop;

use App\Enums\ShopOrderStatus;
use App\Mail\ShopOrderWindowMail;
use App\Models\ShopOrder;
use App\Models\User;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_index_shows_applied_coupon_column(): void
    {
        $admin = User::factory()->admin()->create();
        ShopOrder::factory()->paid()->create([
            'name' => 'Coupon Customer',
            'coupon_code' => 'SAVE10',
            'discount_eur' => 3.00,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee(__('shop.admin.col_coupon'))
            ->assertSee('SAVE10');
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

    public function test_index_distinguishes_in_person_pending_from_unfinished_online_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $inPerson = ShopOrder::factory()->create([
            'name' => 'Pickup Payer',
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
        ]);
        $online = ShopOrder::factory()->create([
            'name' => 'Abandoned Checkout',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        // Request the "Alle" view so the abandoned online pending order is
        // also visible (the default finalized view hides it). An empty
        // status= param (as the form's "Alle" option submits) means "all".
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('data-testid="shop-order-status-pending-in-person"', false)
            ->assertSee('data-testid="shop-order-status-pending-online"', false)
            ->assertSee(__('shop.admin.status_pending_in_person'))
            ->assertSee(__('shop.admin.status_pending_online'))
            ->assertSee(__('shop.admin.pending_online_note'));
    }

    public function test_default_view_shows_only_finalized_orders(): void
    {
        $admin = User::factory()->admin()->create();

        $paid = ShopOrder::factory()->paid()->create(['name' => 'Paid Customer']);
        $fulfilled = ShopOrder::factory()->paid()->create([
            'name' => 'Fulfilled Customer',
            'status' => ShopOrderStatus::Fulfilled,
            'fulfilled_at' => now(),
        ]);
        $inPerson = ShopOrder::factory()->create([
            'name' => 'Pickup Payer',
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
        ]);
        $abandonedOnline = ShopOrder::factory()->create([
            'name' => 'Abandoned Checkout',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);
        $failed = ShopOrder::factory()->create([
            'name' => 'Failed Customer',
            'status' => ShopOrderStatus::Failed,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-row-'.$paid->id.'"', false)
            ->assertSee('data-testid="shop-admin-row-'.$fulfilled->id.'"', false)
            ->assertSee('data-testid="shop-admin-row-'.$inPerson->id.'"', false)
            ->assertDontSee('data-testid="shop-admin-row-'.$abandonedOnline->id.'"', false)
            ->assertDontSee('data-testid="shop-admin-row-'.$failed->id.'"', false);
    }

    public function test_index_shows_addresses_in_fulfillment_column(): void
    {
        $admin = User::factory()->admin()->create();

        $delivery = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Beispielweg 42',
            'delivery_postal_code' => '10999',
        ]);
        $pickup = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee('Beispielweg 42')
            ->assertSee('10999')
            ->assertSee('Karl-Kunger-Straße 9');
    }

    public function test_index_filters_by_delivery_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        $inRange = ShopOrder::factory()->paid()->create([
            'name' => 'In Range',
            'expected_delivery_date' => '2026-06-10',
        ]);
        $alsoInRange = ShopOrder::factory()->paid()->create([
            'name' => 'Also In Range',
            'expected_delivery_date' => '2026-06-12',
        ]);
        $outside = ShopOrder::factory()->paid()->create([
            'name' => 'Outside',
            'expected_delivery_date' => '2026-06-17',
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', [
                'delivery_from' => '2026-06-10',
                'delivery_to' => '2026-06-12',
            ]))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-row-'.$inRange->id.'"', false)
            ->assertSee('data-testid="shop-admin-row-'.$alsoInRange->id.'"', false)
            ->assertDontSee('data-testid="shop-admin-row-'.$outside->id.'"', false);
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

    public function test_admin_can_delete_an_order_and_it_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::PendingPayment,
        ]);
        $orderId = $order->id;

        $this->actingAs($admin)
            ->delete(route('app.admin.shop-orders.destroy', $order))
            ->assertRedirect(route('app.admin.shop-orders.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('shop_orders', ['id' => $orderId]);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'shop_order',
            'entity_id' => $orderId,
            'action' => 'delete',
            'from_state' => 'pending_payment',
        ]);
    }

    public function test_deleting_an_order_cascades_to_its_items(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->hasItems(2)->create();
        $orderId = $order->id;

        $this->assertDatabaseHas('shop_order_items', ['shop_order_id' => $orderId]);

        $this->actingAs($admin)
            ->delete(route('app.admin.shop-orders.destroy', $order))
            ->assertRedirect(route('app.admin.shop-orders.index'));

        $this->assertDatabaseMissing('shop_order_items', ['shop_order_id' => $orderId]);
    }

    public function test_non_admin_cannot_delete_an_order(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $order = ShopOrder::factory()->create();

        $this->actingAs($user)
            ->delete(route('app.admin.shop-orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('shop_orders', ['id' => $order->id]);
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

    public function test_setting_a_window_persists_and_notifies_customer(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'delivery',
            'delivery_window_from' => null,
            'delivery_window_to' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.delivery-window', $order), [
                'window_from' => '14:00',
                'window_to' => '16:00',
            ])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame('14:00 – 16:00', $fresh->deliveryWindowLabel());
        $this->assertNotNull($fresh->delivery_window_set_at);
        Mail::assertQueued(ShopOrderWindowMail::class);
    }

    public function test_saving_the_same_window_does_not_requeue_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'delivery',
            'delivery_window_from' => '14:00',
            'delivery_window_to' => '16:00',
            'delivery_window_set_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.delivery-window', $order), [
                'window_from' => '14:00',
                'window_to' => '16:00',
            ])
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_window_to_before_from_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->paid()->create([
            'delivery_window_from' => null,
            'delivery_window_to' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.delivery-window', $order), [
                'window_from' => '16:00',
                'window_to' => '14:00',
            ])
            ->assertSessionHasErrors('window_to');

        $this->assertNull($order->fresh()->delivery_window_from);
    }

    public function test_clearing_a_window_sends_no_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = ShopOrder::factory()->paid()->create([
            'delivery_window_from' => '14:00',
            'delivery_window_to' => '16:00',
            'delivery_window_set_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.delivery-window', $order), [
                'window_from' => '',
                'window_to' => '',
            ])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertNull($fresh->delivery_window_from);
        $this->assertNull($fresh->delivery_window_set_at);
        Mail::assertNothingQueued();
    }
}
