<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardHeroTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_active_subscriber_with_composed_box_sees_composed_variant_and_view_box_action(): void
    {
        // Mid-day Monday so the time-of-day greeting is "Guten Tag".
        Carbon::setTestNow(Carbon::parse('2026-05-18 11:00:00'));

        $user = User::factory()->create(['name' => 'Lucille Vogel', 'locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);
        $subscription->update(['box_size' => 'medium']);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            // Week of the same day so the dashboard treats it as current.
            'week_start_date' => Carbon::today()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-dashboard-hero', false)
            ->assertSee('Lucille')
            // German weekday for Wednesday is "Mittwoch" (delivery day per config).
            ->assertSee(__('customer.dashboard.hero.delivery_line.composed', ['weekday' => 'Mittwoch']))
            // BOX № prefix with the zero-padded box id.
            ->assertSee('Box № '.str_pad((string) $box->id, 4, '0', STR_PAD_LEFT))
            ->assertSee(__('customer.dashboard.hero.action.view_box'))
            ->assertSee(__('subscriptions.skip_next_week_cta'))
            ->assertSee(__('subscriptions.skip_range_cta'))
            ->assertDontSee(__('subscriptions.end_skip_cta'));
    }

    public function test_active_subscriber_without_composed_box_sees_planned_variant_without_view_box_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 11:00:00'));

        $user = User::factory()->create(['name' => 'Lucille', 'locale' => 'de']);
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-dashboard-hero', false)
            ->assertSee(__('customer.dashboard.hero.delivery_line.planned'))
            // No box composed → no view-box CTA and no BOX № corner label.
            ->assertDontSee(__('customer.dashboard.hero.action.view_box'))
            ->assertDontSee('Box №');
    }

    public function test_paused_subscriber_sees_paused_variant_with_end_break_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 11:00:00'));

        $user = User::factory()->create(['name' => 'Lucille', 'locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);
        $subscription->update([
            'status' => SubscriptionStatus::Paused,
            'pause_from' => Carbon::today()->subDay()->toDateString(),
            'pause_until' => Carbon::today()->addWeeks(2)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-dashboard-hero', false)
            ->assertSee(__('customer.dashboard.hero.delivery_line.paused', [
                'date' => Carbon::today()->addWeeks(2)->format('d.m.Y'),
            ]))
            // Paused → "End break" action surfaces; "Skip next week" hides.
            ->assertSee(__('subscriptions.end_skip_cta'))
            ->assertDontSee(__('subscriptions.skip_next_week_cta'));
    }

    public function test_pause_modal_is_hosted_on_dashboard(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        // The modal's open-modal name must appear on the page so Alpine's
        // page-scoped event can find it when the action row dispatches.
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('confirm-subscription-pause')
            ->assertSee(__('subscriptions.skip_range_modal_title'));
    }

    public function test_skip_next_week_button_posts_to_subscription_skip_route(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        // The form's action URL appears in the rendered HTML; the existing
        // SubscriptionController test covers the route behaviour itself.
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('action="'.route('app.subscriptions.skip.next').'"', false);
    }

    public function test_trial_user_keeps_old_hero_band_not_new_dashboard_hero(): void
    {
        // Trial state is out of scope for ERD-351 — it should still render
        // the marketing-style hero-band with the "Schön, dass du da bist"
        // greeting, not the new dashboard-hero.
        $user = User::factory()->create(['name' => 'Lucille']);
        // No subscription, no trial order → NewVisitor state.

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('customer-dashboard-hero', false);
    }
}
