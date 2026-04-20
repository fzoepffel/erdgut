<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDeliveryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_sync_deliveries_from_boxes(): void
    {
        $this->post(route('app.admin.deliveries.sync-from-boxes'), [])
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_cannot_sync_deliveries_from_boxes(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('app.admin.deliveries.sync-from-boxes'), [])
            ->assertForbidden();
    }

    public function test_admin_sync_from_boxes_redirects_with_flash(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Address::factory()->create(['user_id' => $customer->id, 'is_primary' => true]);

        $monday = Carbon::parse('2028-03-06');
        Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.deliveries.sync-from-boxes'), [
                'from' => $monday->toDateString(),
            ])
            ->assertRedirect(route('app.admin.deliveries.index'))
            ->assertSessionHas('status', 'admin-deliveries-synced')
            ->assertSessionHas('admin_deliveries_sync_count', 1);

        $expectedShip = $monday->copy()->addDays(2)->toDateString();
        $this->assertTrue(Delivery::query()
            ->where('user_id', $customer->id)
            ->where('is_weekly_override', false)
            ->whereDate('scheduled_for_date', $expectedShip)
            ->exists());
    }

    public function test_admin_can_create_canonical_delivery(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $customer->id, 'is_primary' => true]);

        $date = '2029-06-13';

        $this->actingAs($admin)
            ->post(route('app.admin.deliveries.store'), [
                'user_id' => $customer->id,
                'address_id' => $address->id,
                'scheduled_for_date' => $date,
                'timeslot' => '08:00-12:00',
                'status' => DeliveryStatus::Scheduled->value,
                'special_instructions' => 'Leave at door',
                'is_weekly_override' => false,
            ])
            ->assertRedirect(route('app.admin.deliveries.index'))
            ->assertSessionHas('status', 'admin-delivery-created');

        $this->assertTrue(Delivery::query()
            ->where('user_id', $customer->id)
            ->where('address_id', $address->id)
            ->whereDate('scheduled_for_date', $date)
            ->where('timeslot', '08:00-12:00')
            ->where('status', DeliveryStatus::Scheduled)
            ->where('is_weekly_override', false)
            ->exists());
    }

    public function test_admin_cannot_create_second_weekly_override_for_same_base(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $addr1 = Address::factory()->create(['user_id' => $customer->id, 'is_primary' => true]);
        $addr2 = Address::factory()->create(['user_id' => $customer->id]);

        $base = Delivery::factory()->create([
            'user_id' => $customer->id,
            'address_id' => $addr1->id,
            'scheduled_for_date' => '2030-01-09',
            'is_weekly_override' => false,
        ]);

        Delivery::factory()->create([
            'user_id' => $customer->id,
            'address_id' => $addr2->id,
            'scheduled_for_date' => '2030-01-09',
            'is_weekly_override' => true,
            'overrides_delivery_id' => $base->id,
            'status' => DeliveryStatus::Skipped,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.create', ['user_id' => $customer->id]))
            ->post(route('app.admin.deliveries.store'), [
                'user_id' => $customer->id,
                'address_id' => $addr2->id,
                'scheduled_for_date' => '2030-01-09',
                'timeslot' => '12:00-16:00',
                'status' => DeliveryStatus::Skipped->value,
                'is_weekly_override' => true,
                'overrides_delivery_id' => $base->id,
            ])
            ->assertSessionHasErrors('overrides_delivery_id');
    }

    public function test_admin_can_update_canonical_delivery(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $addr1 = Address::factory()->create(['user_id' => $customer->id, 'is_primary' => true]);
        $addr2 = Address::factory()->create(['user_id' => $customer->id]);

        $delivery = Delivery::factory()->create([
            'user_id' => $customer->id,
            'address_id' => $addr1->id,
            'scheduled_for_date' => '2031-04-01',
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('app.admin.deliveries.update', $delivery), [
                'address_id' => $addr2->id,
                'scheduled_for_date' => '2031-04-08',
                'timeslot' => '16:00-20:00',
                'status' => DeliveryStatus::Delivered->value,
                'special_instructions' => 'Updated',
            ])
            ->assertRedirect(route('app.admin.deliveries.index'))
            ->assertSessionHas('status', 'admin-delivery-updated');

        $delivery->refresh();
        $this->assertSame($addr2->id, $delivery->address_id);
        $this->assertSame('2031-04-08', $delivery->scheduled_for_date->toDateString());
        $this->assertSame('16:00-20:00', $delivery->timeslot);
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertSame('Updated', $delivery->special_instructions);
    }

    public function test_admin_create_form_accessible(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.deliveries.create'))
            ->assertOk();
    }
}
