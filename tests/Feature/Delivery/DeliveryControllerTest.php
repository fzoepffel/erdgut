<?php

namespace Tests\Feature\Delivery;

use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_delivery_placeholder_pages(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Delivery override options are coming soon.');

        $this->actingAs($user)
            ->get(route('app.deliveries.gift', $delivery))
            ->assertOk()
            ->assertSee('Gift address flow is coming soon.');
    }

    public function test_owner_can_trigger_skip_placeholder_action(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $this->actingAs($user)
            ->post(route('app.deliveries.skip', $delivery))
            ->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('status', 'skip-week-coming-soon');
    }

    public function test_non_owner_cannot_access_delivery_placeholder_routes(): void
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
            ->get(route('app.deliveries.gift', $delivery))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('app.deliveries.skip', $delivery))
            ->assertForbidden();
    }
}
