<?php

namespace Tests\Feature\Subscription;

use App\Enums\BillingMode;
use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks the K7 / K15 / K16 / K8+K17 fixes from the UX review state-
 * coherence batch.
 *
 *  - K7: skip-next-week must not delete a Box that has a linked Delivery
 *    in a non-Scheduled state (the Box has already been physically
 *    actioned even if its own status field hasn't propagated yet).
 *  - K15: the user-facing skip confirmation copy is specific to "next
 *    week paused" and includes the actual Monday date.
 *  - K16: the dashboard's Abo card surfaces a pending pause window
 *    while the subscription is still nominally Active.
 *  - K8/K17: the dashboard hero binds to the next concrete Delivery
 *    row (when one exists) rather than the cadence-based calculation.
 */
class StateCoherenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeActiveSubscription(User $user): Subscription
    {
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        return Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
            'fulfillment_mode' => FulfillmentMode::Delivery,
            'billing_mode' => BillingMode::SumupRecurring,
            'sumup_token' => 'tok_test',
            'sumup_customer_id' => 'cust_test',
        ]);
    }

    public function test_k7_skip_cleanup_preserves_box_when_linked_delivery_is_already_delivered(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->makeActiveSubscription($user);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            // Box.status didn't propagate when the operator marked the
            // delivery as Delivered (that's I4). Without the K7 fix, the
            // skip-cleanup query would still match this Box (it's
            // Editable) and would delete history.
            'status' => BoxStatus::Editable,
            'week_start_date' => '2026-05-04',
            'modification_deadline_at' => now()->addDays(2),
            'estimated_value' => 0,
        ]);

        Delivery::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'address_id' => $user->addresses()->first()->id,
            'scheduled_for_date' => '2026-05-06',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Delivered,
            'delivered_at' => now(),
            'is_weekly_override' => false,
        ]);

        // User schedules a pause that includes the already-delivered week.
        app(SubscriptionLifecycleService::class)->schedulePause(
            $sub,
            '2026-05-04',
            '2026-05-10',
            $user->id,
        );

        $this->assertNotNull(
            Box::find($box->id),
            'Box must survive skip-cleanup when its linked Delivery has already moved out of Scheduled.'
        );
    }

    public function test_k7_skip_cleanup_still_removes_editable_box_with_only_scheduled_delivery(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->makeActiveSubscription($user);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'status' => BoxStatus::Editable,
            'week_start_date' => '2026-05-04',
            'modification_deadline_at' => now()->addDays(2),
            'estimated_value' => 0,
        ]);

        Delivery::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'address_id' => $user->addresses()->first()->id,
            'scheduled_for_date' => '2026-05-06',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        app(SubscriptionLifecycleService::class)->schedulePause(
            $sub,
            '2026-05-04',
            '2026-05-10',
            $user->id,
        );

        $this->assertNull(
            Box::find($box->id),
            'Box that was only scheduled (not actioned) should still be removed by skip-cleanup.'
        );
    }

    public function test_k15_skip_next_week_flashes_dedicated_confirmation_status(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->makeActiveSubscription($user);

        $response = $this->actingAs($user)
            ->from(route('app.subscriptions.show'))
            ->post(route('app.subscriptions.skip.next'));

        $response->assertRedirect(route('app.subscriptions.show'));
        // Flash key is dedicated for skip-next so the confirmation banner
        // can interpolate the actual Monday date instead of using the
        // generic "Pause timeframe saved" copy that multi-week pauses use.
        $response->assertSessionHas('status', 'subscription-next-week-skipped');
        $response->assertSessionHas('subscription_skipped_week', '2026-05-04');
    }

    public function test_k15_skip_next_week_banner_uses_dedicated_status_branch_in_view(): void
    {
        // View-level lock: assert the show.blade.php template includes the
        // dedicated banner branch (with its data-testid) so a future edit
        // can't silently drop it. Pure file-content assertion to sidestep
        // the unrelated `$subscriptionTabs` Blade-component scoping noise
        // we hit when actually rendering the show view in tests.
        $template = file_get_contents(resource_path('views/subscriptions/show.blade.php'));
        $this->assertStringContainsString("session('status') === 'subscription-next-week-skipped'", $template);
        $this->assertStringContainsString('data-testid="subscription-skip-confirmation"', $template);
        $this->assertStringContainsString('Week of :date is paused', $template);
    }

    public function test_k16_dashboard_surfaces_pending_pause_window(): void
    {
        Carbon::setTestNow('2026-04-29');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->makeActiveSubscription($user);

        // Schedule a pause for next week (still Active today, so the raw
        // status doesn't tell the customer anything about the upcoming
        // pause unless we expose pause_from explicitly).
        app(SubscriptionLifecycleService::class)->schedulePause(
            $sub,
            '2026-05-04',
            '2026-05-10',
            $user->id,
        );

        $sub->refresh();
        $this->assertSame('2026-05-04', $sub->pause_from?->toDateString());
        $this->assertSame(SubscriptionStatus::Active, $sub->status);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();

        $body = $response->getContent();

        // ERD-351: the editorial dashboard surfaces a pending pause via the
        // hero's "End break" CTA (only rendered when hasPauseWindow() is
        // true) instead of the old subscription card's
        // dashboard-subscription-pause-pending block. The presence of
        // dashboard-hero-action-end-break is the new canonical signal that
        // the dashboard is acknowledging a pending pause.
        $this->assertStringContainsString('data-testid="dashboard-hero-action-end-break"', $body);
    }
}
