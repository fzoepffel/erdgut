<?php

namespace Tests\Feature\Subscription;

use App\Enums\BillingMode;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * W2 #3 + data-flow follow-up: when a subscription becomes ready to deliver
 * (Active + sumup_token), the planner should auto-create the Box for the
 * first eligible week AND project the canonical Delivery — admin no longer
 * has to remember to re-open Box-Setup. Tests
 * `WeeklyBoxPlannerService::ensureFirstBoxForActivation` directly because
 * it's the surface the onboarding flow + admin paths share.
 */
class ActivationProjectsFirstBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeReadySubscription(): Subscription
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        return Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
            'fulfillment_mode' => FulfillmentMode::Delivery,
            'billing_mode' => BillingMode::SumupRecurring,
            'sumup_token' => 'tok_test',
            'sumup_customer_id' => 'cust_test',
            'next_charge_date' => null,
        ]);
    }

    public function test_creates_box_and_delivery_for_first_eligible_week(): void
    {
        Carbon::setTestNow('2026-04-29');

        $sub = $this->makeReadySubscription();

        $box = app(WeeklyBoxPlannerService::class)->ensureFirstBoxForActivation($sub);

        $this->assertNotNull($box);
        $this->assertSame('2026-05-04', $box->week_start_date->toDateString()); // Monday of next week
        // The box must be linked back to the subscription. Without this the
        // dashboard query (`whereNotNull('subscription_id')` in
        // DashboardStateResolver::resolveSubscriptionBoxes) hides it from
        // the customer.
        $this->assertSame($sub->id, $box->subscription_id);

        $delivery = Delivery::query()
            ->where('user_id', $sub->user_id)
            ->where('box_id', $box->id)
            ->first();
        $this->assertNotNull($delivery);
        $this->assertSame('2026-05-06', $delivery->scheduled_for_date->toDateString());
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
        $this->assertFalse((bool) $delivery->is_weekly_override);
    }

    public function test_idempotent_when_box_already_exists(): void
    {
        Carbon::setTestNow('2026-04-29');

        $sub = $this->makeReadySubscription();

        $first = app(WeeklyBoxPlannerService::class)->ensureFirstBoxForActivation($sub);
        $second = app(WeeklyBoxPlannerService::class)->ensureFirstBoxForActivation($sub);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Box::query()->where('user_id', $sub->user_id)->count());
        $this->assertSame(1, Delivery::query()->where('user_id', $sub->user_id)->count());
    }

    public function test_returns_null_without_a_payment_token(): void
    {
        Carbon::setTestNow('2026-04-29');

        $sub = $this->makeReadySubscription();
        $sub->forceFill(['sumup_token' => null])->save();

        $box = app(WeeklyBoxPlannerService::class)->ensureFirstBoxForActivation($sub->fresh());

        $this->assertNull($box);
        $this->assertSame(0, Box::query()->where('user_id', $sub->user_id)->count());
    }

    public function test_returns_null_when_subscription_is_paused_indefinitely(): void
    {
        Carbon::setTestNow('2026-04-29');

        $sub = $this->makeReadySubscription();
        $sub->forceFill([
            'pause_from' => '2026-05-01',
            'pause_until' => '2026-12-31',
        ])->save();

        $box = app(WeeklyBoxPlannerService::class)->ensureFirstBoxForActivation($sub->fresh());

        $this->assertNull($box);
    }
}
