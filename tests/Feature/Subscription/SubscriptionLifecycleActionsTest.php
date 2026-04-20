<?php

namespace Tests\Feature\Subscription;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_lifecycle_actions(): void
    {
        $this->post(route('app.subscriptions.pause'), [])->assertRedirect('/auth/login');
        $this->post(route('app.subscriptions.resume'), [])->assertRedirect('/auth/login');
        $this->post(route('app.subscriptions.cancel'), [])->assertRedirect('/auth/login');
    }

    public function test_pause_requires_valid_timeframe(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.show'))
            ->post(route('app.subscriptions.pause'), [
                'pause_from' => now()->addDays(10)->toDateString(),
                'pause_until' => now()->addDays(5)->toDateString(),
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHasErrors(['pause_until']);

        $subscription->refresh();
        $this->assertNull($subscription->pause_from);
        $this->assertNull($subscription->pause_until);
    }

    public function test_user_can_schedule_pause_timeframe(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $pauseFrom = now()->addWeek()->toDateString();
        $pauseUntil = now()->addWeeks(2)->toDateString();

        $this->actingAs($user)
            ->post(route('app.subscriptions.pause'), [
                'pause_from' => $pauseFrom,
                'pause_until' => $pauseUntil,
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-pause-scheduled');

        $subscription->refresh();

        $this->assertSame($pauseFrom, $subscription->pause_from?->toDateString());
        $this->assertSame($pauseUntil, $subscription->pause_until?->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_user_can_resume_subscription_immediately(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Paused,
            'plan' => SubscriptionPlan::Weekly,
            'pause_from' => now()->subDays(1)->toDateString(),
            'pause_until' => now()->addDays(7)->toDateString(),
            'next_charge_date' => null,
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.resume'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-resumed');

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->pause_from);
        $this->assertNull($subscription->pause_until);
        $this->assertNotNull($subscription->next_charge_date);
    }

    public function test_user_can_cancel_subscription_with_optional_reason(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'pause_from' => now()->addDays(3)->toDateString(),
            'pause_until' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.cancel'), [
                'cancellation_reason' => 'Too many deliveries for now.',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-cancelled');

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertSame('Too many deliveries for now.', $subscription->cancellation_reason);
        $this->assertNull($subscription->pause_from);
        $this->assertNull($subscription->pause_until);
    }

    public function test_repeated_cancel_is_blocked_after_first_cancellation(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.cancel'), [
                'cancellation_reason' => 'First cancellation',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-cancelled');

        $cancelledAt = $subscription->fresh()->cancelled_at;

        $this->actingAs($user)
            ->post(route('app.subscriptions.cancel'), [
                'cancellation_reason' => 'Second attempt',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-action-not-allowed');

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame('First cancellation', $subscription->cancellation_reason);
        $this->assertTrue($subscription->cancelled_at?->equalTo($cancelledAt));
    }

    public function test_cancelled_subscription_cannot_be_paused_or_resumed(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.pause'), [
                'pause_from' => now()->addDay()->toDateString(),
                'pause_until' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-action-not-allowed');

        $this->actingAs($user)
            ->post(route('app.subscriptions.resume'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-action-not-allowed');
    }

    public function test_user_cannot_apply_lifecycle_actions_to_other_users_subscription(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherSubscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.pause'), [
                'pause_from' => now()->addDay()->toDateString(),
                'pause_until' => now()->addDays(4)->toDateString(),
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-missing');

        $otherSubscription->refresh();
        $this->assertNull($otherSubscription->pause_from);
        $this->assertNull($otherSubscription->pause_until);
    }

    public function test_pause_exceeding_12_weeks_is_rejected(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.show'))
            ->post(route('app.subscriptions.pause'), [
                'pause_from' => now()->addDay()->toDateString(),
                'pause_until' => now()->addWeeks(13)->toDateString(),
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHasErrors(['pause_until']);
    }

    public function test_resume_of_active_subscription_is_rejected(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.resume'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-action-not-allowed');
    }

    public function test_cancel_reason_exceeding_1000_chars_is_rejected(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.show'))
            ->post(route('app.subscriptions.cancel'), [
                'cancellation_reason' => str_repeat('x', 1001),
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHasErrors(['cancellation_reason']);
    }

    public function test_subscription_overview_renders_skip_controls_and_pause_window(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Paused,
            'pause_from' => '2026-04-01',
            'pause_until' => '2026-04-15',
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->assertSee(__('subscriptions.skip_next_week_cta'))
            ->assertSee(__('subscriptions.skip_range_cta'))
            ->assertSee(__('subscriptions.end_skip_cta'))
            ->assertSee('Cancel subscription')
            ->assertSee('01.04.2026')
            ->assertSee('15.04.2026');
    }

    public function test_user_can_skip_next_week(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.skip.next'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-pause-scheduled');

        $subscription->refresh();

        $nextMonday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
        $this->assertSame($nextMonday->toDateString(), $subscription->pause_from?->toDateString());
        $this->assertSame($nextMonday->endOfWeek()->toDateString(), $subscription->pause_until?->toDateString());
    }

    public function test_skip_next_week_without_subscription_returns_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('app.subscriptions.skip.next'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-missing');
    }
}
