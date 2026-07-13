<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Safety backfill (P5): products written with a bare producer_id (the
 * admin controller did not mirror into the pivot between P1 and P5)
 * get their primary product_producer row. Idempotent.
 */
class ProductProducerPivotBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_07_13_200000_backfill_missing_product_producer_pivots.php');
        $migration->up();
    }

    public function test_backfill_adds_the_missing_primary_pivot_row_idempotently(): void
    {
        $farm = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        // Simulate the pre-P5 admin flow: bare column, no pivot row.
        DB::table('product_producer')->where('product_id', $product->id)->delete();

        $this->runBackfill();
        $this->runBackfill();

        $rows = DB::table('product_producer')->where('product_id', $product->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($farm->id, (int) $rows[0]->producer_id);
        $this->assertSame(0, (int) $rows[0]->position);
    }

    public function test_backfill_leaves_multi_producer_pivots_untouched(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farmA->id]);
        $product->syncProducers([$farmA->id, $farmB->id]);

        $this->runBackfill();

        $rows = DB::table('product_producer')
            ->where('product_id', $product->id)
            ->orderBy('position')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertSame([$farmA->id, $farmB->id], $rows->pluck('producer_id')->map(fn ($v) => (int) $v)->all());
    }
}
