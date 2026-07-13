<?php

namespace Tests\Unit\Models;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Multi-producer foundation: pivot registry, primary mirror, backfill invariants. */
class ProductProducersTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_mirrors_the_producer_into_the_pivot(): void
    {
        $product = Product::factory()->create();

        $this->assertSame(1, $product->producers()->count());
        $this->assertSame($product->producer_id, $product->producers()->first()->id);
        $this->assertSame(0, (int) $product->producers()->first()->pivot->position);
    }

    public function test_sync_producers_orders_pivot_and_mirrors_primary(): void
    {
        $product = Product::factory()->create();
        $a = $product->producer_id;
        $b = Producer::factory()->create()->id;
        $c = Producer::factory()->create()->id;

        $product->syncProducers([$b, $c, $a]);

        $product->refresh();
        $this->assertSame($b, $product->producer_id); // first = primary
        $this->assertSame([$b, $c, $a], $product->producers()->pluck('producers.id')->all());
        $this->assertSame([0, 1, 2], $product->producers()->pluck('product_producer.position')->all());

        // Re-sync drops removed producers.
        $product->syncProducers([$c]);
        $product->refresh();
        $this->assertSame($c, $product->producer_id);
        $this->assertSame(1, $product->producers()->count());
    }

    public function test_sync_producers_rejects_an_empty_set(): void
    {
        $product = Product::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $product->syncProducers([]);
    }

    public function test_backfill_migration_is_idempotent_and_stamps_inventory(): void
    {
        $product = Product::factory()->create();
        // Simulate a pre-backfill world: strip the pivot and the inflow stamp.
        DB::table('product_producer')->where('product_id', $product->id)->delete();
        $inflow = $product->stockInflows()->create([
            'received_on' => now()->toDateString(),
            'original_received_on' => now()->toDateString(),
            'quantity' => 10, 'freshness_window_days' => 2,
        ]);
        DB::table('product_stock_inflows')->where('id', $inflow->id)->update(['producer_id' => null]);

        // Run the backfill twice; both times the invariant must hold, once.
        foreach (range(1, 2) as $i) {
            (require database_path('migrations/2026_07_13_100200_backfill_product_producer_data.php'))->up();
            $this->assertSame(1, DB::table('product_producer')->where('product_id', $product->id)->count());
            $this->assertSame($product->producer_id, (int) DB::table('product_stock_inflows')->where('id', $inflow->id)->value('producer_id'));
        }
    }

    public function test_producer_all_products_unions_legacy_and_pivot(): void
    {
        $primaryProduct = Product::factory()->create();
        $producer = $primaryProduct->producer;

        // A second product where this producer is only a pivot supplier.
        $other = Product::factory()->create();
        $other->syncProducers([$other->producer_id, $producer->id]);

        $ids = $producer->allProducts()->pluck('id')->all();
        $this->assertContains($primaryProduct->id, $ids);
        $this->assertContains($other->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_producer_with_pivot_supplied_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $extra = Producer::factory()->create();
        $product->syncProducers([$product->producer_id, $extra->id]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->delete(route('app.admin.producers.destroy', $extra))
            ->assertRedirect(route('app.admin.producers.index'))
            ->assertSessionHas('error');
        $this->assertNotNull($extra->fresh());
    }
}
