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
}
