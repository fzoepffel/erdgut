<?php

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_deliveries_index_read_only(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
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
            ->get(route('app.deliveries.index'))
            ->assertOk()
            ->assertSee('deliveries.')
            ->assertSee('08:00-12:00')
            ->assertSee('Gift box');

        $response->assertDontSee('Save one-time override');
        $response->assertDontSee('Skip this week');
        $response->assertDontSee('Postpone to next week');
    }

    public function test_show_route_redirects_to_weekly_management_and_focuses_delivery(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.deliveries.show', $delivery))
            ->assertRedirect(route('app.deliveries.index', ['focus' => $delivery->id]));
    }

    public function test_customer_delivery_mutation_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('app.deliveries.skip'));
        $this->assertFalse(Route::has('app.deliveries.postpone'));
        $this->assertFalse(Route::has('app.deliveries.override.update'));
    }

    public function test_guest_redirected_from_deliveries_index(): void
    {
        $this->get(route('app.deliveries.index'))
            ->assertRedirect(route('auth.login'));
    }
}
