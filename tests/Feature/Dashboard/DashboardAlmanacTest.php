<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\SubscriptionSkipWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardAlmanacTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_composed_box_for_this_week_renders_on_its_way_status(): void
    {
        // Wednesday delivery is May 6 2026 (the canonical Wed for ISO week 19).
        // Pin the test to that Monday so the almanac sees this week as
        // "current" and the box as "on its way".
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'week_start_date' => Carbon::parse('2026-05-04')->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-almanac-rows', false)
            // Composed-box label uses the zero-padded box id.
            ->assertSee('Box №'.str_pad((string) $box->id, 4, '0', STR_PAD_LEFT))
            // First row = this week, composed → UNTERWEGS.
            ->assertSee(__('customer.dashboard.almanac.status_on_its_way'));
    }

    public function test_future_uncomposed_weeks_render_seasonal_tagline_and_scheduled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        // No boxes — every row is a future scheduled week with a seasonal tagline.
        // May → tagline_month_5 = "Erdbeer-Höhepunkt".
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-almanac-rows', false)
            ->assertSee(__('customer.dashboard.almanac.tagline_month_5'))
            ->assertSee(__('customer.dashboard.almanac.status_scheduled'));
    }

    public function test_skipped_week_renders_skipped_status_with_pause_label(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);

        // Skip the very next week. The first row (this week) stays scheduled;
        // the second row reads ÜBERSPRUNGEN with the "Zurück ab KW :n" label,
        // because the week after the skip is active again.
        SubscriptionSkipWeek::query()->create([
            'subscription_id' => $subscription->id,
            'week_start_date' => Carbon::parse('2026-05-11')->toDateString(),
            'source' => SubscriptionSkipWeek::SOURCE_USER,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-almanac-rows', false)
            ->assertSee(__('customer.dashboard.almanac.status_skipped'))
            // ISO week 20 → 2026-05-11..05-17. The week after (KW 21) is back active.
            ->assertSee(__('customer.dashboard.almanac.label_back_in_week', ['n' => 21]));
    }

    public function test_paused_subscriber_still_sees_almanac_with_in_pause_label(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);
        // Pause covers the full 4-week horizon.
        $subscription->update([
            'status' => SubscriptionStatus::Paused,
            'pause_from' => Carbon::parse('2026-05-04')->toDateString(),
            'pause_until' => Carbon::parse('2026-06-30')->toDateString(),
        ]);

        // Spec call: paused users still see the almanac so they can verify
        // their pause coverage. Every row reads ÜBERSPRUNGEN with the
        // "In deiner Auszeit" label since no week within the 4-week
        // horizon is active.
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-almanac-rows', false)
            ->assertSee(__('customer.dashboard.almanac.label_in_pause'))
            ->assertSee(__('customer.dashboard.almanac.status_skipped'));
    }
}
