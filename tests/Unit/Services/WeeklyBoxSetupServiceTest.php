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

    private function createStockedProduct(
        Carbon $monday,
        float $unitPrice,
        float $plannedQty,
        string $unit = 'piece',
        ?string $name = null,
        string $category = 'produce',
    ): Product {
        $resolvedName = $name ?? 'Prod-'.uniqid();
        $product = Product::factory()->create([
            'category' => $category,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'is_available' => true,
            'is_published' => true,
            'metadata' => null,
        ]);
        foreach (['de', 'en', 'fr'] as $locale) {
            $product->translations()
                ->where('locale', $locale)
                ->update([
                    'name' => $resolvedName,
                    'origin' => 'regional',
                    'variety' => '-',
                    'taste_profile' => '-',
                ]);
        }
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => $plannedQty,
            'unit' => $unit,
        ]);

        return $product;
    }

    public function test_calendar_overview_returns_horizon_weeks_with_not_set_up_when_no_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        config()->set('admin.box_planner_weeks_ahead', 8);

        $rows = $this->service()->calendarOverview();

        $this->assertCount(8, $rows);
        foreach ($rows as $row) {
            $this->assertSame('not_set_up', $row['status']);
            $this->assertSame(0, $row['eligible_count']);
            $this->assertSame(0, $row['boxes_count']);
        }
    }

    public function test_auto_distribution_respects_remaining_when_week_has_existing_box_items(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $this->createSubscription('small');

        $product = $this->createStockedProduct($monday, 2.0, 10, unit: 'piece', name: 'RemainProd', category: 'produce');

        // Existing allocation already consumed 9 out of 10 units for that week.
        // Use a non-eligible box owner so weekly setup cannot reuse it as a representative template.
        $otherUser = User::factory()->create();
        $existingBox = Box::factory()->create([
            'user_id' => $otherUser->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
        ]);
        BoxItem::factory()->for($existingBox)->create([
            'product_id' => $product->id,
            'quantity' => 9,
            'is_extra' => false,
        ]);

        $data = $this->service()->buildInitialDistribution($monday);
        $small = collect($data['size_blocks'])->firstWhere('box_size', 'small');
        $this->assertNotNull($small);

        $line = collect($small['lines'])->firstWhere('product_id', $product->id);
        $this->assertNotNull($line);

        // Only 1 unit should be allocatable due to remaining stock.
        $this->assertSame(1, (int) $line['quantity']);
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

    public function test_build_initial_distribution_allocates_with_round_robin_variety(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $this->createSubscription('small');

        $p5 = $this->createStockedProduct($monday, 5.0, 20, category: 'produce');
        $p3 = $this->createStockedProduct($monday, 3.0, 20, category: 'produce');
        $p2 = $this->createStockedProduct($monday, 2.0, 20, category: 'produce');

        $data = $this->service()->buildInitialDistribution($monday);

        $this->assertSame($monday->toDateString(), $data['week_start_date']);
        $small = collect($data['size_blocks'])->firstWhere('box_size', 'small');
        $this->assertNotNull($small);
        $this->assertSame(1, $small['n']);
        $this->assertEqualsWithDelta(20.0, $small['max'], 0.001);
        $this->assertEqualsWithDelta(14.0, $small['target'], 0.001);
        $this->assertLessThanOrEqual($small['target'] + 0.005, $small['retail_sum']);
        $this->assertFalse($small['over_target']);
        $this->assertFalse($small['from_existing']);

        $qtyByPid = collect($small['lines'])->pluck('quantity', 'product_id');
        $this->assertGreaterThanOrEqual(1, $qtyByPid[$p5->id] ?? 0);
        $this->assertGreaterThanOrEqual(1, $qtyByPid[$p3->id] ?? 0);
        $this->assertGreaterThanOrEqual(1, $qtyByPid[$p2->id] ?? 0);

        $maxQty = $qtyByPid->max();
        $minQty = $qtyByPid->min();
        $this->assertLessThanOrEqual(1, $maxQty - $minQty);
    }

    public function test_build_initial_distribution_orders_by_category_then_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $this->createSubscription('small');

        // D6/D7/F8 (UX-review notes): the category taxonomy collapsed
        // vegetables + fruit into one "produce" enum value; this test
        // still wants to verify category-then-price ordering, so the
        // third product moves to eggs_and_dairy (the second canonical
        // category in `composition_by_size.small`) — produce comes
        // before eggs_and_dairy in $baseOrder, then by price DESC
        // within each.
        $vegA = $this->createStockedProduct($monday, 3.0, 20, category: 'produce');
        $vegB = $this->createStockedProduct($monday, 2.0, 20, category: 'produce');
        $fruitC = $this->createStockedProduct($monday, 4.0, 20, category: 'eggs_and_dairy');

        $data = $this->service()->buildInitialDistribution($monday);
        $small = collect($data['size_blocks'])->firstWhere('box_size', 'small');
        $qtyByPid = collect($small['lines'])->pluck('quantity', 'product_id');

        $this->assertSame(2, $qtyByPid[$vegA->id] ?? 0);
        $this->assertSame(2, $qtyByPid[$vegB->id] ?? 0);
        $this->assertSame(1, $qtyByPid[$fruitC->id] ?? 0);
        $this->assertEqualsWithDelta(14.0, $small['retail_sum'], 0.001);
    }

    public function test_build_initial_distribution_loads_existing_box_lines(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $sub = $this->createSubscription('small');

        $prodA = $this->createStockedProduct($monday, 5.0, 20, category: 'produce');
        $prodB = $this->createStockedProduct($monday, 3.0, 20, category: 'produce');

        $box = Box::factory()->create([
            'user_id' => $sub->user_id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $prodA->id,
            'quantity' => 4,
            'is_extra' => false,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $prodB->id,
            'quantity' => 2,
            'is_extra' => false,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $prodA->id,
            'is_extra' => true,
        ]);

        $data = $this->service()->buildInitialDistribution($monday);

        $this->assertTrue($data['has_existing']);

        $small = collect($data['size_blocks'])->firstWhere('box_size', 'small');
        $this->assertNotNull($small);
        $this->assertTrue($small['from_existing']);

        $lines = collect($small['lines'])->keyBy('product_id');
        $this->assertSame(4, (int) ($lines[$prodA->id]['quantity'] ?? 0));
        $this->assertSame(2, (int) ($lines[$prodB->id]['quantity'] ?? 0));
        $this->assertCount(2, $small['lines']);
    }

    public function test_build_initial_distribution_flags_missing_stock_products_from_existing_box(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));
        $monday = Carbon::parse('2026-05-04');

        $sub = $this->createSubscription('small');

        $orphanProduct = Product::factory()->create([
            'unit_price' => 4.0,
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);

        $box = Box::factory()->create([
            'user_id' => $sub->user_id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $orphanProduct->id,
            'quantity' => 3,
            'is_extra' => false,
        ]);

        $data = $this->service()->buildInitialDistribution($monday);

        $stock = collect($data['stock'])->firstWhere('product_id', $orphanProduct->id);
        $this->assertNotNull($stock);
        $this->assertTrue($stock['missing_stock']);
        $this->assertEqualsWithDelta(0.0, $stock['planned'], 0.001);
        $this->assertEqualsWithDelta(3.0, $stock['used_total'], 0.001);
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
