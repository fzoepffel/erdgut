<?php

namespace Tests\Unit\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * W2-22 + W2-24: the dashboard's "next delivery" used to read
 * `next_charge_date` directly, which could be in the past for new subs
 * (it's a billing date, not a ship date). The model now exposes the
 * canonical Wednesday of the first cadence-matching, non-paused week.
 */
class SubscriptionFirstDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_returns_null_when_subscription_is_not_active(): void
    {
        Carbon::setTestNow('2026-04-29'); // Wed
        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Cancelled,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $this->assertNull($sub->firstExpectedDeliveryDate());
    }

    public function test_weekly_returns_next_wednesday_after_today(): void
    {
        // Today is Wed 29 Apr 2026. This week's Wed has already passed
        // (it IS today's date, but we treat "today" as past for delivery
        // planning since composition runs the day before). Expect next Wed.
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'next_charge_date' => null,
        ]);

        $this->assertSame('2026-05-06', $sub->firstExpectedDeliveryDate()?->toDateString());
    }

    public function test_weekly_uses_next_charge_date_when_in_future(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'next_charge_date' => '2026-05-25', // future, in week of Mon 25 May
        ]);

        // Wed of week-of-25-May is 2026-05-27.
        $this->assertSame('2026-05-27', $sub->firstExpectedDeliveryDate()?->toDateString());
    }

    public function test_weekly_ignores_next_charge_date_in_the_past(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'next_charge_date' => '2026-04-15',
        ]);

        $this->assertSame('2026-05-06', $sub->firstExpectedDeliveryDate()?->toDateString());
    }

    public function test_biweekly_skips_odd_weeks(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Biweekly,
        ]);

        // Mon 4 May 2026 is ISO week 19 (odd). The next even-week Monday
        // is 11 May (week 20). Wed of that week is 13 May.
        $this->assertSame('2026-05-13', $sub->firstExpectedDeliveryDate()?->toDateString());
    }

    public function test_returns_null_inside_indefinite_pause_window(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'pause_from' => '2026-05-01',
            'pause_until' => '2026-12-31', // wider than the 26-week search horizon
        ]);

        $this->assertNull($sub->firstExpectedDeliveryDate());
    }

    public function test_skips_weeks_inside_a_finite_pause_window(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create();
        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'pause_from' => '2026-05-04',  // pauses week of May 4
            'pause_until' => '2026-05-15',
        ]);

        // The first un-paused Monday is 2026-05-18; Wed is 20 May.
        $this->assertSame('2026-05-20', $sub->firstExpectedDeliveryDate()?->toDateString());
    }
}
