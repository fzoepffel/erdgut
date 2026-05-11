<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Services\Admin\RecurringStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecurringStockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(RecurringStockService::class);
        Carbon::setTestNow('2026-05-04 10:00:00'); // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_indefinite_schedule_materializes_default_horizon(): void
    {
        $product = Product::factory()->create(['unit' => 'kg']);

        $schedule = $this->service->createSchedule(
            product: $product,
            plannedQuantity: 10.0,
            startsOnMonday: Carbon::parse('2026-05-04'),
            endsOnMonday: null,
        );

        $rows = ProductWeekStock::query()
            ->where('product_id', $product->id)
            ->where('source_schedule_id', $schedule->id)
            ->orderBy('week_start_date')
            ->get();

        $this->assertCount(RecurringStockService::MATERIALIZE_WEEKS_AHEAD + 1, $rows);
        $this->assertSame('2026-05-04', $rows->first()->week_start_date->toDateString());
        $this->assertEquals(10.00, (float) $rows->first()->planned_quantity);
    }

    public function test_create_bounded_schedule_only_writes_in_window(): void
    {
        $product = Product::factory()->create(['unit' => 'kg']);

        $this->service->createSchedule(
            product: $product,
            plannedQuantity: 5.0,
            startsOnMonday: Carbon::parse('2026-05-04'),
            endsOnMonday: Carbon::parse('2026-05-25'),
        );

        $rows = ProductWeekStock::query()->where('product_id', $product->id)->get();
        $this->assertCount(4, $rows); // May 4, 11, 18, 25
    }

    public function test_cancel_from_deletes_future_unmodified_rows_only(): void
    {
        $product = Product::factory()->create(['unit' => 'kg']);

        $schedule = $this->service->createSchedule(
            product: $product,
            plannedQuantity: 10.0,
            startsOnMonday: Carbon::parse('2026-05-04'),
            endsOnMonday: null,
        );

        // Admin overrides week 4 (2026-05-25) with a different quantity.
        ProductWeekStock::query()
            ->where('product_id', $product->id)
            ->whereDate('week_start_date', '2026-05-25')
            ->update(['planned_quantity' => 4.5]);

        $this->service->cancelFrom($schedule->id, Carbon::parse('2026-05-18'));

        // 2026-05-04, 2026-05-11 (before cutoff) stay
        // 2026-05-18 deleted (matches schedule quantity)
        // 2026-05-25 stays (manual override at 4.5kg)
        // Everything beyond 2026-05-25 deleted

        $this->assertTrue(ProductWeekStock::query()->whereDate('week_start_date', '2026-05-04')->exists());
        $this->assertTrue(ProductWeekStock::query()->whereDate('week_start_date', '2026-05-11')->exists());
        $this->assertFalse(ProductWeekStock::query()->whereDate('week_start_date', '2026-05-18')->exists());
        $this->assertTrue(ProductWeekStock::query()->whereDate('week_start_date', '2026-05-25')->exists());
        $this->assertFalse(ProductWeekStock::query()->whereDate('week_start_date', '2026-06-01')->exists());

        $schedule->refresh();
        $this->assertSame('2026-05-11', $schedule->ends_on_monday->toDateString());
    }

    public function test_adjust_from_ends_old_creates_new(): void
    {
        $product = Product::factory()->create(['unit' => 'kg']);

        $old = $this->service->createSchedule(
            product: $product,
            plannedQuantity: 10.0,
            startsOnMonday: Carbon::parse('2026-05-04'),
            endsOnMonday: null,
        );

        $new = $this->service->adjustFrom(
            scheduleId: $old->id,
            fromMonday: Carbon::parse('2026-06-01'),
            newQuantity: 15.0,
        );

        $old->refresh();
        $this->assertSame('2026-05-25', $old->ends_on_monday->toDateString());
        $this->assertSame('2026-06-01', $new->starts_on_monday->toDateString());
        $this->assertEquals(15.00, (float) $new->planned_quantity);

        // Week of 2026-05-25 still has the OLD quantity
        $row = ProductWeekStock::query()->whereDate('week_start_date', '2026-05-25')->first();
        $this->assertEquals(10.00, (float) $row->planned_quantity);

        // Week of 2026-06-01 has the NEW quantity
        $row = ProductWeekStock::query()->whereDate('week_start_date', '2026-06-01')->first();
        $this->assertEquals(15.00, (float) $row->planned_quantity);
        $this->assertSame($new->id, $row->source_schedule_id);
    }

    public function test_materialize_window_idempotent(): void
    {
        $product = Product::factory()->create(['unit' => 'kg']);

        $this->service->createSchedule(
            product: $product,
            plannedQuantity: 10.0,
            startsOnMonday: Carbon::parse('2026-05-04'),
            endsOnMonday: null,
        );

        $beforeCount = ProductWeekStock::query()->count();
        $written = $this->service->materializeWindow(Carbon::parse('2026-09-01'));
        $afterCount = ProductWeekStock::query()->count();

        // Some new rows may have been added if the cron horizon is beyond
        // the default. Running the same materialize again must add zero.
        $written = $this->service->materializeWindow(Carbon::parse('2026-09-01'));
        $this->assertSame(0, $written, 'Second materialize should be a no-op.');
    }
}
