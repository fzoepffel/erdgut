<?php

namespace Tests\Feature\Subscription;

use App\Enums\BillingMode;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks K9 (notes file) — every Box created for a subscriber MUST carry
 * `subscription_id`. The customer dashboard's "Aktuelle und kommende
 * Boxen" panel filters with `whereNotNull('subscription_id')`, so any
 * subscriber box created without this link is silently invisible to
 * the customer it belongs to.
 *
 * Two creation paths to lock down:
 *   - WeeklyBoxPlannerService::ensureEmptyBox()
 *   - WeeklyBoxPlannerService::createBoxWithPresetIfMissing()
 *
 * The third Box::create in the codebase (WeeklyBoxSetupService::applyTrialOrderLines)
 * is for trial orders (uses order_id) — `subscription_id` correctly stays
 * null there, which is verified separately by the trial flow tests.
 */
class WeeklyBoxPlannerSubscriptionLinkTest extends TestCase
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
            'box_size' => 'medium',
            'fulfillment_mode' => FulfillmentMode::Delivery,
            'billing_mode' => BillingMode::SumupRecurring,
            'sumup_token' => 'tok_test',
            'sumup_customer_id' => 'cust_test',
        ]);
    }

    public function test_ensure_empty_box_links_to_subscription(): void
    {
        Carbon::setTestNow('2026-04-29');

        $sub = $this->makeReadySubscription();
        $weekStart = Carbon::parse('2026-05-04'); // Monday

        $box = app(WeeklyBoxPlannerService::class)->ensureEmptyBox($sub, $weekStart);

        $this->assertSame($sub->id, $box->subscription_id);
        $this->assertSame($sub->user_id, $box->user_id);
        $this->assertSame('2026-05-04', $box->week_start_date->toDateString());

        // Re-running on the same week is idempotent and still surfaces the linked box.
        $boxAgain = app(WeeklyBoxPlannerService::class)->ensureEmptyBox($sub, $weekStart);
        $this->assertSame($box->id, $boxAgain->id);
        $this->assertSame($sub->id, $boxAgain->subscription_id);
    }

    public function test_create_box_with_preset_if_missing_links_to_subscription(): void
    {
        Carbon::setTestNow('2026-04-29');

        $sub = $this->makeReadySubscription();
        $weekStart = Carbon::parse('2026-05-04'); // Monday

        $created = app(WeeklyBoxPlannerService::class)->createBoxWithPresetIfMissing($sub, $weekStart);

        $this->assertTrue($created);

        $box = Box::query()
            ->where('user_id', $sub->user_id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->whereNull('order_id')
            ->first();

        $this->assertNotNull($box);
        $this->assertSame($sub->id, $box->subscription_id);

        // Second call doesn't create another row — and the first one stays linked.
        $second = app(WeeklyBoxPlannerService::class)->createBoxWithPresetIfMissing($sub, $weekStart);
        $this->assertFalse($second);
        $this->assertSame(1, Box::query()->where('user_id', $sub->user_id)->count());
    }
}
