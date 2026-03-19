<?php

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_weekly_delivery_management_page(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->addDay()->toDateString(),
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.deliveries.index'));

        $response
            ->assertOk()
            ->assertSee('Weekly deliveries')
            ->assertSee('Save one-time override')
            ->assertSee('08:00-12:00');
    }

    public function test_show_route_redirects_to_weekly_management_and_focuses_delivery(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.deliveries.show', $delivery))
            ->assertRedirect(route('app.deliveries.index', ['focus' => $delivery->id]));
    }

    public function test_owner_can_create_and_update_weekly_override_without_mutating_base_delivery(): void
    {
        $user = User::factory()->create();
        $baseAddress = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        $overrideAddress = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => false,
        ]);

        $baseDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $baseAddress->id,
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => 'Leave at the gate.',
        ]);

        $this->actingAs($user)->patch(route('app.deliveries.override.update', $baseDelivery), [
            'address_id' => $overrideAddress->id,
            'timeslot' => '16:00-20:00',
            'special_instructions' => 'Call on arrival.',
            'skip_week' => '1',
        ])->assertRedirect(route('app.deliveries.index', ['focus' => $baseDelivery->id]));

        $this->assertDatabaseHas('deliveries', [
            'user_id' => $user->id,
            'overrides_delivery_id' => $baseDelivery->id,
            'is_weekly_override' => true,
            'address_id' => $overrideAddress->id,
            'timeslot' => '16:00-20:00',
            'status' => DeliveryStatus::Skipped->value,
            'special_instructions' => 'Call on arrival.',
        ]);

        $this->assertDatabaseHas('deliveries', [
            'id' => $baseDelivery->id,
            'address_id' => $baseAddress->id,
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled->value,
            'special_instructions' => 'Leave at the gate.',
        ]);

        $this->actingAs($user)->patch(route('app.deliveries.override.update', $baseDelivery), [
            'address_id' => $overrideAddress->id,
            'timeslot' => '12:00-16:00',
            'special_instructions' => 'Use side entrance.',
            'skip_week' => '0',
        ])->assertRedirect(route('app.deliveries.index', ['focus' => $baseDelivery->id]));

        $this->assertSame(
            1,
            Delivery::query()->where('overrides_delivery_id', $baseDelivery->id)->count()
        );
        $this->assertDatabaseHas('deliveries', [
            'overrides_delivery_id' => $baseDelivery->id,
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled->value,
            'special_instructions' => 'Use side entrance.',
        ]);
    }

    public function test_owner_can_reset_override_to_defaults_and_delete_override_record(): void
    {
        $user = User::factory()->create();
        $baseAddress = Address::factory()->create(['user_id' => $user->id]);
        $overrideAddress = Address::factory()->create(['user_id' => $user->id]);
        $baseDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $baseAddress->id,
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => 'Default instructions.',
        ]);
        $overrideDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $overrideAddress->id,
            'scheduled_for_date' => $baseDelivery->scheduled_for_date->toDateString(),
            'timeslot' => '16:00-20:00',
            'status' => DeliveryStatus::Skipped,
            'special_instructions' => 'Override instructions.',
            'is_weekly_override' => true,
            'overrides_delivery_id' => $baseDelivery->id,
        ]);

        $this->actingAs($user)->patch(route('app.deliveries.override.update', $baseDelivery), [
            'reset_override' => '1',
        ])->assertRedirect(route('app.deliveries.index', ['focus' => $baseDelivery->id]));

        $this->assertDatabaseMissing('deliveries', [
            'id' => $overrideDelivery->id,
        ]);
    }

    public function test_non_owner_cannot_access_or_update_weekly_delivery_management_routes(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $owner->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $owner->id,
            'address_id' => $address->id,
        ]);

        $this->actingAs($otherUser)
            ->get(route('app.deliveries.show', $delivery))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('app.deliveries.override.update', $delivery), [
                'timeslot' => '12:00-16:00',
            ])
            ->assertForbidden();
    }

    public function test_override_update_rejects_address_not_owned_by_authenticated_user(): void
    {
        $user = User::factory()->create();
        $foreignUser = User::factory()->create();
        $userAddress = Address::factory()->create(['user_id' => $user->id]);
        $foreignAddress = Address::factory()->create(['user_id' => $foreignUser->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $userAddress->id,
        ]);

        $this->actingAs($user)
            ->from(route('app.deliveries.index'))
            ->patch(route('app.deliveries.override.update', $delivery), [
                'address_id' => $foreignAddress->id,
            ])
            ->assertRedirect(route('app.deliveries.index'))
            ->assertSessionHasErrors('address_id');
    }

    public function test_override_update_rejects_invalid_timeslot_value(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $this->actingAs($user)
            ->from(route('app.deliveries.index'))
            ->patch(route('app.deliveries.override.update', $delivery), [
                'timeslot' => '23:00-00:00',
            ])
            ->assertRedirect(route('app.deliveries.index'))
            ->assertSessionHasErrors('timeslot');
    }

    public function test_skip_week_is_rejected_for_delivered_delivery(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'status' => DeliveryStatus::Delivered,
        ]);

        $this->actingAs($user)
            ->patch(route('app.deliveries.override.update', $delivery), [
                'skip_week' => '1',
            ])
            ->assertRedirect(route('app.deliveries.index', ['focus' => $delivery->id]))
            ->assertSessionHas('status', 'delivery-weekly-override-not-allowed');

        $this->assertSame(
            0,
            Delivery::query()->where('overrides_delivery_id', $delivery->id)->count()
        );
    }
}
