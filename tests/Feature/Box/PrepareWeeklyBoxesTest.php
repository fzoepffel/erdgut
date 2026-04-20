<?php

namespace Tests\Feature\Box;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PrepareWeeklyBoxesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_eligible_subscriptions(): void
    {
        $user = User::factory()->create(['email' => 'box@example.com']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->artisan('boxes:prepare', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 box(es) would be created for '.$monday);
    }

    public function test_creates_box_for_active_weekly_subscriber(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->artisan('boxes:prepare')
            ->assertSuccessful()
            ->expectsOutputToContain('Created 1 box(es)');

        $box = Box::forUser($subscription->user_id)->forWeek($monday)->first();
        $this->assertNotNull($box);
        $this->assertSame(BoxStatus::Editable, $box->status);
        $this->assertEquals(0, (float) $box->estimated_value);
    }

    public function test_creates_canonical_delivery_when_subscriber_has_primary_address(): void
    {
        $user = User::factory()->create();
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->artisan('boxes:prepare', ['--week' => $monday])
            ->assertSuccessful();

        $expectedShipDate = Carbon::parse($monday)->addDays(2)->toDateString();

        $this->assertTrue(Delivery::query()
            ->where('user_id', $user->id)
            ->where('is_weekly_override', false)
            ->whereDate('scheduled_for_date', $expectedShipDate)
            ->where('status', DeliveryStatus::Scheduled)
            ->exists());
    }

    public function test_skips_existing_box_for_same_user_and_week(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY);

        Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $monday->toDateString(),
        ]);

        $this->artisan('boxes:prepare')
            ->assertSuccessful()
            ->expectsOutputToContain('Skipped 1');

        $this->assertSame(1, Box::forUser($user->id)->forWeek($monday->toDateString())->count());
    }

    public function test_excludes_paused_subscription_within_window(): void
    {
        $user = User::factory()->create();
        $monday = Carbon::today()->next(Carbon::MONDAY);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'pause_from' => $monday->copy()->subDays(3),
            'pause_until' => $monday->copy()->addDays(3),
        ]);

        $this->artisan('boxes:prepare')
            ->assertSuccessful()
            ->expectsOutputToContain('No eligible subscriptions');
    }

    public function test_excludes_cancelled_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Cancelled,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $this->artisan('boxes:prepare')
            ->assertSuccessful()
            ->expectsOutputToContain('No eligible subscriptions');
    }

    public function test_biweekly_subscription_only_gets_box_on_even_weeks(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Biweekly,
            'box_size' => 'large',
        ]);

        $evenWeekMonday = Carbon::today()->next(Carbon::MONDAY);
        while ((int) $evenWeekMonday->weekOfYear % 2 !== 0) {
            $evenWeekMonday->addWeek();
        }

        $this->artisan('boxes:prepare', ['--week' => $evenWeekMonday->toDateString()])
            ->assertSuccessful()
            ->expectsOutputToContain('Created 1 box(es)');

        $oddWeekMonday = $evenWeekMonday->copy()->addWeek();

        $this->artisan('boxes:prepare', ['--week' => $oddWeekMonday->toDateString()])
            ->assertSuccessful()
            ->expectsOutputToContain('No eligible subscriptions');
    }

    public function test_accepts_custom_week_option(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $targetWeek = '2026-06-01';

        $this->artisan('boxes:prepare', ['--week' => $targetWeek])
            ->assertSuccessful()
            ->expectsOutputToContain('Created 1 box(es) for 2026-06-01');

        $box = Box::forUser($user->id)->forWeek('2026-06-01')->first();
        $this->assertNotNull($box);
    }

    public function test_sets_modification_deadline_two_days_before_week_start(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $targetWeek = '2026-07-06';

        $this->artisan('boxes:prepare', ['--week' => $targetWeek])
            ->assertSuccessful();

        $box = Box::forUser($user->id)->forWeek($targetWeek)->first();
        $this->assertNotNull($box);
        $this->assertSame('2026-07-04', $box->modification_deadline_at->toDateString());
    }

    public function test_creates_boxes_for_multiple_subscribers(): void
    {
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            Subscription::factory()->create([
                'user_id' => $user->id,
                'status' => SubscriptionStatus::Active,
                'plan' => SubscriptionPlan::Weekly,
            ]);
        }

        $this->artisan('boxes:prepare')
            ->assertSuccessful()
            ->expectsOutputToContain('Created 3 box(es)');
    }

    public function test_no_eligible_subscriptions_outputs_message(): void
    {
        $this->artisan('boxes:prepare')
            ->assertSuccessful()
            ->expectsOutputToContain('No eligible subscriptions');
    }
}
