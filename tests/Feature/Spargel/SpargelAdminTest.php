<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the disposable /spargel-admin trial-shop view.
 *
 * Surface is intentionally small: list, mark-paid, mark-completed.
 * Tests cover the auth gate + the two row-level actions.
 */
class SpargelAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('spargel.admin.index'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('spargel.admin.index'))
            ->assertForbidden();
    }

    /**
     * Surfaced in production 2026-05-04: a customer who got declined by SumUp
     * and clicked "Try again" reset the order back to PendingPayment, leaving
     * no visible trace of the failed attempt in the admin list. The admin row
     * now shows a "❌ N failed attempts" badge whenever payment_attempts > 0,
     * regardless of current status, so the history doesn't evaporate on retry.
     */
    public function test_failed_payment_attempts_render_in_admin_row(): void
    {
        $admin = User::factory()->admin()->create();

        // After-retry state: status reset to PendingPayment but counter persisted.
        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
            'payment_attempts' => 2,
            'last_payment_failed_at' => now()->subMinutes(5),
        ]);

        // Pin the request locale to de via the session (the SetLocale
        // middleware reads from session > Accept-Language > app default) so
        // we exercise the customer-facing pluralization that production sees.
        $html = $this->actingAs($admin)
            ->withSession(['locale' => 'de'])
            ->get(route('spargel.admin.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('spargel-admin-failed-attempts', $html);
        $this->assertStringContainsString('2 gescheiterte Versuche', $html);
    }

    public function test_failed_attempts_badge_is_absent_when_no_attempts(): void
    {
        $admin = User::factory()->admin()->create();
        SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
            'payment_attempts' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('spargel.admin.index'))
            ->assertOk()
            ->assertDontSee('spargel-admin-failed-attempts');
    }

    public function test_admin_sees_all_orders_with_customer_details(): void
    {
        $admin = User::factory()->admin()->create();
        $order = SpargelOrder::factory()->create([
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'quantity_green_grams' => 500,
            'quantity_white_grams' => 200,
            'fulfillment_mode' => 'pickup',
            'payment_method' => 'online',
            'status' => SpargelOrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('spargel.admin.index'))
            ->assertOk()
            ->assertSee('Anna Mustermann')
            ->assertSee('anna@example.com')
            ->assertSee('500 g')
            ->assertSee('200 g')
            ->assertSee('data-testid="spargel-admin-row-'.$order->id.'"', false);
    }

    public function test_empty_state_renders_when_no_orders_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('spargel.admin.index'))
            ->assertOk()
            ->assertSee(__('spargel.admin.empty'));
    }

    public function test_mark_paid_flips_pending_in_person_order_to_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $order = SpargelOrder::factory()->create([
            'payment_method' => 'in_person',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $this->actingAs($admin)
            ->post(route('spargel.admin.mark-paid', $order))
            ->assertRedirect(route('spargel.admin.index'))
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->marked_paid_by_admin_at);
    }

    public function test_mark_paid_is_no_op_when_order_is_already_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $order = SpargelOrder::factory()->paid()->create();
        $originalPaidAt = $order->paid_at;

        $this->actingAs($admin)
            ->post(route('spargel.admin.mark-paid', $order))
            ->assertRedirect(route('spargel.admin.index'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Paid, $order->status);
        // paid_at should NOT be touched on a no-op so we don't overwrite
        // the original SumUp confirmation timestamp.
        $this->assertEquals($originalPaidAt->toDateTimeString(), $order->paid_at->toDateTimeString());
        $this->assertNull($order->marked_paid_by_admin_at);
    }

    public function test_mark_completed_sets_fulfilled_status_and_timestamp(): void
    {
        $admin = User::factory()->admin()->create();
        $order = SpargelOrder::factory()->paid()->create();

        $this->actingAs($admin)
            ->post(route('spargel.admin.mark-completed', $order))
            ->assertRedirect(route('spargel.admin.index'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Fulfilled, $order->status);
        $this->assertNotNull($order->fulfilled_at);
    }

    public function test_mark_completed_on_pending_order_marks_paid_and_done_in_one_step(): void
    {
        // Convenience for the in-person flow: admin sees the customer at
        // pickup, takes cash, hands over the spargel — one click is
        // enough to record both transitions.
        $admin = User::factory()->admin()->create();
        $order = SpargelOrder::factory()->create([
            'payment_method' => 'in_person',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $this->actingAs($admin)
            ->post(route('spargel.admin.mark-completed', $order))
            ->assertRedirect(route('spargel.admin.index'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Fulfilled, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->marked_paid_by_admin_at);
        $this->assertNotNull($order->fulfilled_at);
    }

    public function test_non_admin_cannot_mark_paid(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $this->actingAs($user)
            ->post(route('spargel.admin.mark-paid', $order))
            ->assertForbidden();

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);
    }

    public function test_admin_can_delete_an_order_via_destroy_route(): void
    {
        $admin = User::factory()->admin()->create();
        $order = SpargelOrder::factory()->create();
        $orderId = $order->id;

        $this->actingAs($admin)
            ->delete(route('spargel.admin.destroy', $order))
            ->assertRedirect(route('spargel.admin.index'));

        $this->assertNull(SpargelOrder::find($orderId));
    }

    public function test_non_admin_cannot_delete_an_order(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $order = SpargelOrder::factory()->create();
        $orderId = $order->id;

        $this->actingAs($user)
            ->delete(route('spargel.admin.destroy', $order))
            ->assertForbidden();

        $this->assertNotNull(SpargelOrder::find($orderId));
    }
}
