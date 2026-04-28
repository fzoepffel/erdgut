<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardClickEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardClickEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_posting_a_click(): void
    {
        $this->postJson(route('app.dashboard.click'), [
            'section' => 'glance.next_delivery',
            'destination' => 'app.deliveries.index',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_record_a_click(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('app.dashboard.click'), [
                'section' => 'glance.next_delivery',
                'destination' => 'app.deliveries.index',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('dashboard_click_events', [
            'user_id' => $user->id,
            'section' => 'glance.next_delivery',
            'destination' => 'app.deliveries.index',
        ]);
    }

    public function test_validation_rejects_missing_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('app.dashboard.click'), [
                'destination' => 'app.deliveries.index',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);
    }

    public function test_validation_caps_field_lengths(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('app.dashboard.click'), [
                'section' => str_repeat('x', 51),
                'destination' => 'app.deliveries.index',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);
    }

    public function test_purge_command_deletes_events_older_than_retention_window(): void
    {
        $user = User::factory()->create();

        $oldEvent = DashboardClickEvent::query()->create([
            'user_id' => $user->id,
            'section' => 'glance.next_delivery',
            'destination' => 'app.deliveries.index',
            'locale' => 'de',
            'occurred_at' => Carbon::now()->subDays(120),
        ]);

        $recentEvent = DashboardClickEvent::query()->create([
            'user_id' => $user->id,
            'section' => 'feed.almanach.box_row',
            'destination' => 'app.boxes.show',
            'locale' => 'de',
            'occurred_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('dashboard:purge-click-events')->assertSuccessful();

        $this->assertDatabaseMissing('dashboard_click_events', ['id' => $oldEvent->id]);
        $this->assertDatabaseHas('dashboard_click_events', ['id' => $recentEvent->id]);
    }

    public function test_purge_command_respects_custom_days_option(): void
    {
        $user = User::factory()->create();

        DashboardClickEvent::query()->create([
            'user_id' => $user->id,
            'section' => 'glance.next_delivery',
            'destination' => 'app.deliveries.index',
            'locale' => 'de',
            'occurred_at' => Carbon::now()->subDays(20),
        ]);

        $this->artisan('dashboard:purge-click-events', ['--days' => 7])->assertSuccessful();

        $this->assertSame(0, DashboardClickEvent::query()->count());
    }
}
