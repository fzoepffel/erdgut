<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_order_create(): void
    {
        $this->get(route('app.admin.orders.create'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_cannot_access_admin_order_create(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.orders.create'))
            ->assertForbidden();
    }

    public function test_admin_can_render_create_form_without_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.orders.create'))
            ->assertOk()
            ->assertSee('name="user_id"', false)
            ->assertSee('name="address_id"', false);
    }

    public function test_admin_can_render_create_form_with_user_addresses(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_admin' => false]);
        $address = Address::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)
            ->get(route('app.admin.orders.create', ['user_id' => $customer->id]))
            ->assertOk()
            ->assertSee($address->street_line_1);
    }

    public function test_admin_creates_trial_order_on_user_behalf(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_admin' => false]);
        $address = Address::factory()->create(['user_id' => $customer->id]);

        $response = $this->actingAs($admin)
            ->post(route('app.admin.orders.store'), [
                'user_id' => $customer->id,
                'address_id' => $address->id,
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '1'],
                'preferred_delivery_window' => 'next week',
                'special_instructions' => 'Leave at door.',
                'reason' => 'Phone order; will pay in cash.',
            ]);

        $order = Order::query()->where('user_id', $customer->id)->sole();

        $response->assertRedirect(route('app.admin.orders.show', $order))
            ->assertSessionHas('status', 'admin-order-created');

        $this->assertSame(OrderProductType::TrialBox, $order->product_type);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('medium', (string) $order->box_size);
        $this->assertSame($address->id, $order->address_id);
        $this->assertSame('next week', $order->preferred_delivery_window);
        $this->assertSame('Leave at door.', $order->special_instructions);
        $this->assertTrue((bool) ($order->preferences['veg_only'] ?? false));

        $event = AuditEvent::query()
            ->where('entity_type', 'order')
            ->where('entity_id', $order->id)
            ->where('action', 'admin_create')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame('Phone order; will pay in cash.', $event->reason);
        $this->assertSame(OrderProductType::TrialBox->value, $event->metadata['product_type'] ?? null);
    }

    public function test_admin_cannot_create_order_with_address_of_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);
        $otherAddress = Address::factory()->create(['user_id' => $other->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.orders.create', ['user_id' => $customer->id]))
            ->post(route('app.admin.orders.store'), [
                'user_id' => $customer->id,
                'address_id' => $otherAddress->id,
                'box_size' => 'medium',
            ])
            ->assertSessionHasErrors('address_id');

        $this->assertSame(0, Order::query()->where('user_id', $customer->id)->count());
    }

    public function test_admin_cannot_create_order_for_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $address = Address::factory()->create(['user_id' => $otherAdmin->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.orders.create'))
            ->post(route('app.admin.orders.store'), [
                'user_id' => $otherAdmin->id,
                'address_id' => $address->id,
                'box_size' => 'medium',
            ])
            ->assertSessionHasErrors('user_id');
    }
}
