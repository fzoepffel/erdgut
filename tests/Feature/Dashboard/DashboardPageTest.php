<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_dashboard_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Next deliveries')
            ->assertSee('Current and upcoming boxes');
    }

    public function test_dashboard_only_shows_authenticated_user_data(): void
    {
        $dashboardUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $dashboardAddress = Address::factory()->create([
            'user_id' => $dashboardUser->id,
            'city' => 'Berlin Mitte',
        ]);
        $otherAddress = Address::factory()->create([
            'user_id' => $otherUser->id,
            'city' => 'Hamburg Nord',
        ]);

        Delivery::factory()->create([
            'user_id' => $dashboardUser->id,
            'address_id' => $dashboardAddress->id,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
        ]);
        Delivery::factory()->create([
            'user_id' => $otherUser->id,
            'address_id' => $otherAddress->id,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'timeslot' => '16:00-20:00',
            'status' => DeliveryStatus::Scheduled,
        ]);

        Box::factory()->create([
            'user_id' => $dashboardUser->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);
        Box::factory()->create([
            'user_id' => $otherUser->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Locked,
            'estimated_value' => 88.88,
        ]);

        $this->actingAs($dashboardUser)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Berlin Mitte')
            ->assertSee('EUR 39.99')
            ->assertDontSee('Hamburg Nord')
            ->assertDontSee('EUR 88.88');
    }

    public function test_dashboard_orders_and_limits_upcoming_deliveries(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        for ($dayOffset = 1; $dayOffset <= 5; $dayOffset++) {
            Delivery::factory()->create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'scheduled_for_date' => now()->addDays($dayOffset)->toDateString(),
                'timeslot' => sprintf('slot-%d', $dayOffset),
                'status' => DeliveryStatus::Scheduled,
            ]);
        }

        Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->subDay()->toDateString(),
            'timeslot' => 'past-slot',
            'status' => DeliveryStatus::Scheduled,
        ]);

        Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->addDays(1)->toDateString(),
            'timeslot' => 'skipped-slot',
            'status' => DeliveryStatus::Skipped,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['slot-1', 'slot-2', 'slot-3', 'slot-4'])
            ->assertDontSee('slot-5')
            ->assertDontSee('past-slot')
            ->assertDontSee('skipped-slot');
    }

    public function test_dashboard_shows_empty_states_when_no_data_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('No upcoming deliveries yet.')
            ->assertSee('No boxes available yet.');
    }
}
