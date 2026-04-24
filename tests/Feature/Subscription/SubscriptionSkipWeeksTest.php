<?php

namespace Tests\Feature\Subscription;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use App\Models\User;
use App\Support\ScheduledChargeEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionSkipWeeksTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pick_non_contiguous_weeks_to_skip(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $nextMonday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
        $weekA = $nextMonday->toDateString();
        $weekC = $nextMonday->addWeeks(2)->toDateString();
        $weekE = $nextMonday->addWeeks(4)->toDateString();

        $this->actingAs($user)
            ->post(route('app.subscriptions.skip.weeks'), [
                'weeks' => [$weekA, $weekC, $weekE],
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-skip-weeks-updated');

        $this->assertSame(
            [$weekA, $weekC, $weekE],
            $subscription->skipWeeks()
                ->where('source', SubscriptionSkipWeek::SOURCE_USER)
                ->orderBy('week_start_date')
                ->pluck('week_start_date')
                ->map(fn ($d) => $d->toDateString())
                ->all()
        );
    }

    public function test_resubmitting_replaces_previous_user_skip_weeks(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $nextMonday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);

        SubscriptionSkipWeek::create([
            'subscription_id' => $subscription->id,
            'week_start_date' => $nextMonday->toDateString(),
            'source' => SubscriptionSkipWeek::SOURCE_USER,
        ]);

        $newWeek = $nextMonday->addWeeks(3)->toDateString();

        $this->actingAs($user)
            ->post(route('app.subscriptions.skip.weeks'), ['weeks' => [$newWeek]])
            ->assertSessionHas('status', 'subscription-skip-weeks-updated');

        $this->assertSame(
            [$newWeek],
            $subscription->skipWeeks()
                ->where('source', SubscriptionSkipWeek::SOURCE_USER)
                ->pluck('week_start_date')
                ->map(fn ($d) => $d->toDateString())
                ->all()
        );
    }

    public function test_validation_rejects_non_monday_dates(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);

        $nonMonday = CarbonImmutable::today()->next(CarbonImmutable::WEDNESDAY)->toDateString();

        $this->actingAs($user)
            ->from(route('app.subscriptions.show'))
            ->post(route('app.subscriptions.skip.weeks'), ['weeks' => [$nonMonday]])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHasErrors(['weeks.0']);
    }

    public function test_eligibility_returns_false_when_fulfillment_week_is_skipped(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'next_charge_date' => CarbonImmutable::today()->toDateString(),
            'sumup_token' => 'tok_test',
        ]);

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        SubscriptionSkipWeek::create([
            'subscription_id' => $subscription->id,
            'week_start_date' => $weekStart->toDateString(),
            'source' => SubscriptionSkipWeek::SOURCE_USER,
        ]);

        $this->assertFalse(ScheduledChargeEligibility::isDue($subscription->fresh(), Carbon::today()));
    }

    public function test_schedule_pause_writes_pause_window_skip_rows(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $nextMonday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
        $from = $nextMonday->toDateString();
        $until = $nextMonday->addWeeks(2)->endOfWeek()->toDateString();

        $this->actingAs($user)
            ->post(route('app.subscriptions.pause'), [
                'pause_from' => $from,
                'pause_until' => $until,
            ])
            ->assertSessionHas('status', 'subscription-pause-scheduled');

        $rows = $subscription->skipWeeks()
            ->where('source', SubscriptionSkipWeek::SOURCE_PAUSE_WINDOW)
            ->orderBy('week_start_date')
            ->pluck('week_start_date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $this->assertCount(3, $rows);
        $this->assertSame($nextMonday->toDateString(), $rows[0]);
    }
}
