<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Product;
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
            ->assertSee('Current week box')
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
            'scheduled_for_date' => now()->addDays(2)->startOfWeek()->addDays(2)->toDateString(),
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
            ->assertSee('Scheduled')
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
            ->assertSee('Change delivery')
            ->assertSee('Gift box')
            ->assertDontSee('slot-5')
            ->assertDontSee('past-slot')
            ->assertDontSee('skipped-slot');
    }

    public function test_dashboard_shows_delivery_deadline_and_modify_box_action_when_editable_box_exists(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $deliveryDate = now()->addDays(3)->startOfWeek()->addDays(2);

        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => $deliveryDate->toDateString(),
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
        ]);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $deliveryDate->copy()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addWeek(),
        ]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk()
            ->assertSee('Modify by:')
            ->assertSee('Modify box')
            ->assertSee(route('app.boxes.show', $box), false)
            ->assertSee(route('app.deliveries.show', $delivery), false)
            ->assertSee(route('app.deliveries.gift', $delivery), false)
            ->assertSee(route('app.deliveries.skip', $delivery), false);
    }

    public function test_dashboard_shows_current_week_box_summary_with_items_and_value(): void
    {
        $user = User::factory()->create();

        $currentBox = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 44.20,
        ]);
        $product = Product::factory()->create(['name' => 'Test Carrots']);
        BoxItem::factory()->create([
            'box_id' => $currentBox->id,
            'product_id' => $product->id,
            'quantity' => 2.5,
            'unit' => 'kg',
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Current week box')
            ->assertSee('Test Carrots')
            ->assertSee('2.50 kg')
            ->assertSee('Estimated value: EUR 44.20');
    }

    public function test_dashboard_shows_empty_states_when_no_data_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('No upcoming deliveries yet.')
            ->assertSee('No box for this week.')
            ->assertSee('No boxes available yet.')
            ->assertDontSee('Modify box');
    }
}
