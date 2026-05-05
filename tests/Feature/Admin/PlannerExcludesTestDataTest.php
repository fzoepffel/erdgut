<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingMode;
use App\Enums\FulfillmentMode;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks H3 (UX notes): demo / test users seeded with `is_test=true`
 * must not show up alongside real customers in the box-setup planner.
 *
 * The planner exposes opt-in (`includeTest=true`) for the rare case
 * where ops actually wants to debug demo data.
 */
class PlannerExcludesTestDataTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeSubscription(User $user, bool $isTest): Subscription
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
            'is_test' => $isTest,
        ]);
    }

    public function test_eligible_subscriptions_excludes_test_subs_by_default(): void
    {
        Carbon::setTestNow('2026-04-29');

        $real = User::factory()->create(['name' => 'Real customer']);
        $demo = User::factory()->create(['name' => 'Demo seed', 'is_test' => true]);

        $this->makeSubscription($real, false);
        $this->makeSubscription($demo, true);

        $subs = app(WeeklyBoxPlannerService::class)
            ->eligibleSubscriptionsForWeek(Carbon::parse('2026-05-04'));

        $userIds = $subs->pluck('user_id')->all();
        $this->assertContains($real->id, $userIds);
        $this->assertNotContains($demo->id, $userIds);
    }

    public function test_eligible_subscriptions_includes_test_subs_when_opted_in(): void
    {
        Carbon::setTestNow('2026-04-29');

        $real = User::factory()->create();
        $demo = User::factory()->create(['is_test' => true]);

        $this->makeSubscription($real, false);
        $this->makeSubscription($demo, true);

        $subs = app(WeeklyBoxPlannerService::class)
            ->eligibleSubscriptionsForWeek(Carbon::parse('2026-05-04'), includeTest: true);

        $userIds = $subs->pluck('user_id')->all();
        $this->assertContains($real->id, $userIds);
        $this->assertContains($demo->id, $userIds);
    }

    public function test_eligible_trial_orders_excludes_test_orders_by_default(): void
    {
        Carbon::setTestNow('2026-04-29');

        $realUser = User::factory()->create();
        $demoUser = User::factory()->create(['is_test' => true]);

        $real = Order::factory()->for($realUser)->create([
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'scheduled_for_date' => '2026-05-06',
            'is_test' => false,
        ]);
        $demo = Order::factory()->for($demoUser)->create([
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'scheduled_for_date' => '2026-05-06',
            'is_test' => true,
        ]);

        $orders = app(WeeklyBoxPlannerService::class)
            ->eligibleTrialOrdersForWeek(Carbon::parse('2026-05-04'));

        $orderIds = $orders->pluck('id')->all();
        $this->assertContains($real->id, $orderIds);
        $this->assertNotContains($demo->id, $orderIds);
    }
}
