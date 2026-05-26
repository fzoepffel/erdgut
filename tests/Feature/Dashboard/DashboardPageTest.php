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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_authenticated_user_can_view_dashboard_page(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('This week at a glance')
            // ERD-353: the small "Producer of the week" spotlight is replaced
            // by the new landscape <x-customer.producer-feature> for steady-
            // state subscribers — same producer-of-week label still surfaces.
            ->assertSee(__('customer.dashboard.producer_feature.eyebrow'))
            ->assertSee('Next deliveries')
            ->assertSee('Current week box')
            // ERD-355: the legacy "Current and upcoming boxes" card is
            // replaced by the new <x-customer.almanac-rows> with its own
            // title.
            ->assertSee(__('customer.dashboard.almanac.title'));
    }

    public function test_dashboard_only_shows_authenticated_user_data(): void
    {
        $dashboardUser = User::factory()->create();
        $dashboardSubscription = $this->subscribeUserFully($dashboardUser);
        $otherUser = User::factory()->create();
        $otherSubscription = $this->subscribeUserFully($otherUser);

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
            'scheduled_for_date' => now()->addWeek()->startOfWeek()->addDays(2)->toDateString(),
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
            'subscription_id' => $dashboardSubscription->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);
        Box::factory()->create([
            'user_id' => $otherUser->id,
            'subscription_id' => $otherSubscription->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Locked,
            'estimated_value' => 88.88,
        ]);

        $this->actingAs($dashboardUser)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Berlin Mitte')
            ->assertSee('Scheduled')
            ->assertDontSee('Hamburg Nord')
            ->assertDontSee('EUR 88,88');
        // ERD-355: the legacy almanach showed each box's estimated value
        // ("EUR 39,99"). The new <x-customer.almanac-rows> identifies
        // boxes by their zero-padded id instead, so this user's box id
        // is what should now appear in place of the estimated value.
        // We don't assert on it here because it's covered by
        // DashboardAlmanacTest. The intent of THIS test is data isolation:
        // the other user's box (EUR 88,88) must never leak.
    }

    public function test_dashboard_orders_and_limits_upcoming_deliveries(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
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

        // Use a distinct date so we don't violate the new
        // unique(user_id, scheduled_for_date, is_weekly_override) index
        // (day+1 already has a Scheduled delivery from the loop above).
        Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->addDays(10)->toDateString(),
            'timeslot' => 'skipped-slot',
            'status' => DeliveryStatus::Skipped,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['slot-1', 'slot-2', 'slot-3', 'slot-4'])
            ->assertSee('Gift box')
            ->assertDontSee('slot-5')
            ->assertDontSee('past-slot')
            ->assertDontSee('skipped-slot');
    }

    public function test_dashboard_shows_delivery_slot_when_latest_weekly_override_is_skipped(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        $baseAddress = Address::factory()->create(['user_id' => $user->id, 'city' => 'Berlin']);
        $overrideAddress = Address::factory()->create(['user_id' => $user->id, 'city' => 'Leipzig']);

        $baseDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $baseAddress->id,
            'scheduled_for_date' => now()->addDays(3)->toDateString(),
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $overrideAddress->id,
            'scheduled_for_date' => $baseDelivery->scheduled_for_date->toDateString(),
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Skipped,
            'is_weekly_override' => true,
            'overrides_delivery_id' => $baseDelivery->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('12:00-16:00')
            ->assertSee('Leipzig')
            ->assertSee('Skipped', false);
    }

    public function test_dashboard_uses_effective_weekly_override_delivery_values_without_duplicates(): void
    {
        // Pin to a calendar week whose producer-of-the-week rotation
        // doesn't land on Jakob — his bio mentions "Berlin" and would
        // collide with the assertDontSee('Berlin') below. ISO week 4 ↦
        // index (4-1) % 6 = 3 = Marcus.
        Carbon::setTestNow(Carbon::parse('2026-01-21 09:00:00'));

        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        $baseAddress = Address::factory()->create([
            'user_id' => $user->id,
            'city' => 'Berlin',
        ]);
        $overrideAddress = Address::factory()->create([
            'user_id' => $user->id,
            'city' => 'Munich',
        ]);

        $baseDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $baseAddress->id,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $overrideDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $overrideAddress->id,
            'scheduled_for_date' => $baseDelivery->scheduled_for_date->toDateString(),
            'timeslot' => '16:00-20:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => true,
            'overrides_delivery_id' => $baseDelivery->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('16:00-20:00')
            ->assertDontSee('08:00-12:00')
            ->assertSee('Munich')
            ->assertDontSee('Berlin')
            ->assertSee(route('app.deliveries.show', $overrideDelivery), false);
    }

    public function test_dashboard_shows_delivery_deadline_and_modify_box_action_when_editable_box_exists(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $deliveryDate = now()->addWeek()->startOfWeek()->addDays(2);

        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => $deliveryDate->toDateString(),
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
        ]);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'week_start_date' => $deliveryDate->copy()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addWeek(),
        ]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk()
            ->assertSee(route('app.boxes.show', ['box' => $box]), false)
            ->assertSee(route('app.deliveries.gift', $delivery), false);

        $response->assertDontSee('Modify box');
        $response->assertDontSee('Skip week');
    }

    public function test_dashboard_shows_current_week_box_summary_with_items_and_value(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);

        $currentBox = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 44.20,
        ]);
        $product = Product::factory()->create();
        $product->translations()->where('locale', 'en')->update(['name' => 'Test Carrots']);
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
            ->assertSee('Estimated value: EUR 44,20');
    }

    public function test_dashboard_shows_empty_states_when_no_data_exists(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="empty-state"', false)
            ->assertSee('No deliveries on the calendar')
            ->assertSee('No box for this week')
            // ERD-355: the legacy "No boxes scheduled" empty state in the
            // old vertical-card almanach is gone — the new almanac always
            // renders four rows, falling back to a seasonal tagline when
            // no box is composed for that week. So we assert on the new
            // almanac structure instead.
            ->assertSee('customer-almanac-rows', false)
            ->assertDontSee('Modify box');
    }
}
