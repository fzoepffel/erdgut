<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
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

        // Bottom-rail cleanup: the legacy "Next deliveries" and "Current week
        // box" cards are removed for steady-state subscribers — their content
        // is now covered by the new hero, manifest grid, and almanac rows.
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('This week at a glance')
            ->assertSee(__('customer.dashboard.producer_feature.eyebrow'))
            ->assertSee(__('customer.dashboard.almanac.title'))
            ->assertDontSee('Next deliveries')
            ->assertDontSee('Current week box');
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

        // Cross-user data isolation. The new dashboard doesn't expose city
        // or estimated-value strings (those lived in the old bottom rail),
        // so the assertion is restricted to "the other user's distinctive
        // data must never appear on this dashboard".
        $this->actingAs($dashboardUser)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('Hamburg Nord')
            ->assertDontSee('EUR 88,88');
    }

    // Bottom-rail cleanup removed these tests:
    //
    // - test_dashboard_orders_and_limits_upcoming_deliveries
    // - test_dashboard_shows_delivery_slot_when_latest_weekly_override_is_skipped
    // - test_dashboard_uses_effective_weekly_override_delivery_values_without_duplicates
    // - test_dashboard_shows_delivery_deadline_and_modify_box_action_when_editable_box_exists
    // - test_dashboard_shows_current_week_box_summary_with_items_and_value
    //
    // All of these asserted content that lived inside the legacy "Next
    // deliveries" / "Current week box" cards in the dashboard bottom rail.
    // The redesigned dashboard surfaces equivalent information through
    // dedicated components (each with its own test):
    //
    //   - hero shows the next delivery weekday;
    //   - manifest grid shows current-box items;
    //   - almanac rows show the upcoming-week schedule.
    //
    // The order-timeline behavior the deleted tests exercised (override
    // resolution, slot ordering, gift link, etc.) belongs on the
    // /app/deliveries index — DeliveryControllerTest covers that route.

    public function test_dashboard_renders_the_new_editorial_sections_for_steady_state_subscribers(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        // The new dashboard renders all six top-level sections; the legacy
        // rail cards must NOT render anymore.
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-dashboard-hero', false)
            ->assertSee('customer-almanac-rows', false)
            ->assertDontSee('Next deliveries')
            ->assertDontSee('Current week box');
    }
}
