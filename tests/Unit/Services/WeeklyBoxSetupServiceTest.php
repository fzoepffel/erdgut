<?php

namespace Tests\Unit\Services;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\WeeklyBoxSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WeeklyBoxSetupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('box.included_value_by_size', [
            'small' => 20.0,
            'medium' => 35.0,
            'large' => 42.0,
        ]);
        Config::set('box.admin_planning_margin_rate', 0.30);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    private function service(): WeeklyBoxSetupService
    {
        return app(WeeklyBoxSetupService::class);
    }

    private function createSubscription(string $size): Subscription
    {
        $user = User::factory()->create();

        return Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => $size,
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);
    }

    private function createStockedProduct(Carbon $monday, float $unitPrice, float $plannedQty, string $unit = 'piece'): Product
    {
        $product = Product::factory()->create([
            'unit_price' => $unitPrice,
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => $unit],
        ]);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => $plannedQty,
            'unit' => $unit,
        ]);

        return $product;
    }

    public function test_calendar_overview_returns_four_weeks_with_not_set_up_when_no_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $rows = $this->service()->calendarOverview();

        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertSame('not_set_up', $row['status']);
            $this->assertSame(0, $row['eligible_count']);
            $this->assertSame(0, $row['boxes_count']);
        }
    }

    public function test_calendar_overview_marks_partial_when_some_boxes_exist(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $subA = $this->createSubscription('small');
        $this->createSubscription('small');

        Box::factory()->create([
            'user_id' => $subA->user_id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        $rows = $this->service()->calendarOverview();
        $target = collect($rows)->firstWhere('week_start_date', $monday->toDateString());

        $this->assertNotNull($target);
        $this->assertSame('partial', $target['status']);
        $this->assertSame(2, $target['eligible_count']);
        $this->assertSame(1, $target['boxes_count']);
    }

    public function test_build_initial_distribution_allocates_within_target_and_max(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $this->createSubscription('small');

        $this->createStockedProduct($monday, 5.0, 20);
        $this->createStockedProduct($monday, 3.0, 20);
        $this->createStockedProduct($monday, 2.0, 20);

        $data = $this->service()->buildInitialDistribution($monday);

        $this->assertSame($monday->toDateString(), $data['week_start_date']);
        $this->assertNotEmpty($data['size_blocks']);

        $small = collect($data['size_blocks'])->firstWhere('box_size', 'small');
        $this->assertNotNull($small);
        $this->assertSame(1, $small['n']);
        $this->assertEqualsWithDelta(20.0, $small['max'], 0.001);
        $this->assertEqualsWithDelta(14.0, $small['target'], 0.001);
        $this->assertLessThanOrEqual($small['max'] + 0.005, $small['retail_sum']);
        $this->assertFalse($small['over_target']);
    }

    public function test_build_initial_distribution_returns_empty_blocks_when_no_subscriptions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');
        $this->createStockedProduct($monday, 5.0, 20);

        $data = $this->service()->buildInitialDistribution($monday);

        $this->assertSame([], $data['size_blocks']);
    }

    public function test_apply_distribution_creates_boxes_and_box_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $sub = $this->createSubscription('small');
        $product = $this->createStockedProduct($monday, 2.0, 10);

        $updated = $this->service()->applyDistribution($monday, [
            'small' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $this->assertSame(1, $updated);

        $box = Box::query()
            ->where('user_id', $sub->user_id)
            ->whereDate('week_start_date', $monday->toDateString())
            ->first();

        $this->assertNotNull($box);
        $this->assertSame(1, $box->items()->count());
        $this->assertEqualsWithDelta(6.0, (float) $box->fresh()->estimated_value, 0.01);
    }

    public function test_apply_distribution_skips_locked_boxes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $sub = $this->createSubscription('small');

        $locked = Box::factory()->create([
            'user_id' => $sub->user_id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Locked,
        ]);
        BoxItem::factory()->create([
            'box_id' => $locked->id,
            'is_extra' => false,
        ]);

        $product = $this->createStockedProduct($monday, 2.0, 10);

        $updated = $this->service()->applyDistribution($monday, [
            'small' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $this->assertSame(0, $updated);
        $this->assertDatabaseMissing('box_items', [
            'box_id' => $locked->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_apply_distribution_skips_invalid_product_ids(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $sub = $this->createSubscription('small');
        $valid = $this->createStockedProduct($monday, 2.0, 10);

        $unpublished = Product::factory()->create([
            'is_available' => true,
            'is_published' => false,
        ]);

        $updated = $this->service()->applyDistribution($monday, [
            'small' => [
                ['product_id' => $valid->id, 'quantity' => 1],
                ['product_id' => $unpublished->id, 'quantity' => 1],
                ['product_id' => 999999, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(1, $updated);

        $box = Box::query()
            ->where('user_id', $sub->user_id)
            ->whereDate('week_start_date', $monday->toDateString())
            ->first();

        $this->assertNotNull($box);
        $this->assertSame(1, $box->items()->count());
        $this->assertSame($valid->id, $box->items()->first()->product_id);
    }
}
