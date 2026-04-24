<?php

namespace Tests\Unit\Services;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Box;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionLifecycleService;
use App\Support\SubscriptionSkipCleanupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleComputeNextChargeDateTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionLifecycleService
    {
        return new SubscriptionLifecycleService(app(SubscriptionSkipCleanupService::class));
    }

    public function test_defers_to_monday_after_trial_delivery_week_when_trial_has_scheduled_box(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-04')); // Monday

        $user = User::factory()->create();
        $box = Box::factory()->create(['week_start_date' => '2026-05-11']); // next Monday
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Scheduled,
            'box_id' => $box->id,
            'scheduled_for_date' => '2026-05-13',
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $this->assertSame('2026-05-18', $this->service()->computeNextChargeDate($subscription));
    }

    public function test_defers_for_biweekly_plan_to_monday_after_trial_delivery_week(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-04'));

        $user = User::factory()->create();
        $box = Box::factory()->create(['week_start_date' => '2026-05-11']);
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'box_id' => $box->id,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Biweekly,
        ]);

        // Monday after trial delivery week — regardless of biweekly cadence.
        $this->assertSame('2026-05-18', $this->service()->computeNextChargeDate($subscription));
    }

    public function test_falls_back_to_plan_cadence_when_no_trial_order_exists(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-04'));

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $this->assertSame('2026-05-11', $this->service()->computeNextChargeDate($subscription));
    }

    public function test_falls_back_when_trial_delivery_already_past(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-18'));

        $user = User::factory()->create();
        $box = Box::factory()->create(['week_start_date' => '2026-05-04']); // two weeks ago
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Delivered,
            'box_id' => $box->id,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        // Delivered trial orders are not open — fall through.
        $this->assertSame('2026-05-25', $this->service()->computeNextChargeDate($subscription));
    }

    public function test_falls_back_when_trial_has_no_box_or_scheduled_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-04'));

        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'box_id' => null,
            'scheduled_for_date' => null,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $this->assertSame('2026-05-11', $this->service()->computeNextChargeDate($subscription));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }
}
