<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $delivery = Delivery::factory()->create();

        $this->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $delivery = Delivery::factory()->create();

        $this->actingAs($user)
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertForbidden();
    }

    public function test_admin_can_mark_delivery_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertRedirect(route('app.admin.deliveries.index'))
            ->assertSessionHas('status', 'admin-delivery-status-updated');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
    }

    public function test_admin_can_mark_delivery_skipped(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'skipped'])
            ->assertRedirect(route('app.admin.deliveries.index'));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Skipped, $delivery->status);
    }

    public function test_admin_cannot_set_invalid_status(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'not-a-status'])
            ->assertSessionHasErrors('status');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
    }
}
