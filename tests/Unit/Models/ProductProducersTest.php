<?php

namespace Tests\Unit\Models;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductProducerVariantPrice;
use App\Models\ProductVariant;
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

    public function test_buying_price_for_producer_prefers_the_pivot_override(): void
    {
        $product = Product::factory()->create(['buying_price_cents' => 200]);
        $farmA = (int) $product->producer_id;
        $farmB = Producer::factory()->create()->id;
        $product->syncProducers([$farmA, $farmB]);
        $product->producers()->updateExistingPivot($farmB, ['buying_price_cents' => 250]);
        $product->refresh();

        $this->assertSame(2.5, $product->buyingPriceForProducer($farmB));   // override
        $this->assertSame(2.0, $product->buyingPriceForProducer($farmA));   // default fallback
        $this->assertSame(2.0, $product->buyingPriceForProducer(null));     // no farm = default
        $this->assertSame(2.0, $product->buyingPriceForProducer(0));        // unattributable = default

        $product->forceFill(['buying_price_cents' => null])->save();
        $product->refresh();
        $this->assertSame(2.5, $product->buyingPriceForProducer($farmB));   // override without default
        $this->assertNull($product->buyingPriceForProducer($farmA));        // nothing set anywhere
    }

    public function test_max_buying_price_spans_default_and_overrides(): void
    {
        $product = Product::factory()->create(['buying_price_cents' => 200]);
        $farmA = (int) $product->producer_id;
        $farmB = Producer::factory()->create()->id;
        $product->syncProducers([$farmA, $farmB]);
        $product->producers()->updateExistingPivot($farmB, ['buying_price_cents' => 320]);
        $product->refresh();

        // With and without the loaded relation.
        $this->assertSame(3.2, $product->load('producers')->maxBuyingPriceEur());
        $this->assertSame(3.2, Product::query()->findOrFail($product->id)->maxBuyingPriceEur());

        $product->forceFill(['buying_price_cents' => null])->save();
        $product->producers()->updateExistingPivot($farmB, ['buying_price_cents' => null]);
        $this->assertNull(Product::query()->findOrFail($product->id)->maxBuyingPriceEur());
    }

    public function test_grade_price_chain_resolves_override_then_farm_then_default(): void
    {
        $product = Product::factory()->create(['buying_price_cents' => 200]);
        $farm = (int) $product->producer_id;
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $product->producers()->updateExistingPivot($farm, ['buying_price_cents' => 250]);

        // No grade row yet: farm price wins for the grade.
        $fresh = Product::query()->findOrFail($product->id);
        $this->assertSame(250, $fresh->buyingPriceCentsForProducer($farm, $variant->id));

        ProductProducerVariantPrice::create([
            'product_id' => $product->id, 'producer_id' => $farm,
            'variant_id' => $variant->id, 'buying_price_cents' => 320,
        ]);

        // Grade override wins; with and without loaded relations.
        $loaded = Product::query()->with(['producers', 'producerVariantPrices'])->findOrFail($product->id);
        $this->assertSame(320, $loaded->buyingPriceCentsForProducer($farm, $variant->id));
        $this->assertSame(320, Product::query()->findOrFail($product->id)->buyingPriceCentsForProducer($farm, $variant->id));
        // Default grade untouched by the variant row.
        $this->assertSame(250, $loaded->buyingPriceCentsForProducer($farm));
        $this->assertSame(200, $loaded->buyingPriceCentsForProducer(null));
    }

    public function test_max_buying_price_for_variant_spans_the_effective_chain(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farmA->id, 'buying_price_cents' => 200]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $product->producers()->updateExistingPivot($farmB->id, ['buying_price_cents' => 250]);
        ProductProducerVariantPrice::create([
            'product_id' => $product->id, 'producer_id' => $farmA->id,
            'variant_id' => $variant->id, 'buying_price_cents' => 400,
        ]);

        $fresh = Product::query()->findOrFail($product->id);
        // Variant: farm A effective 400 (grade row), farm B 250 (pivot) → 400.
        $this->assertSame(400, $fresh->maxBuyingPriceCentsForVariant($variant->id));
        // Default grade: max(default 200, pivot 250) → 250.
        $this->assertSame(250, $fresh->maxBuyingPriceCentsForVariant(null));
    }

    public function test_sync_producers_preserves_pivot_buying_prices_on_reorder(): void
    {
        $product = Product::factory()->create();
        $farmA = (int) $product->producer_id;
        $farmB = Producer::factory()->create()->id;
        $product->syncProducers([$farmA, $farmB]);
        $product->producers()->updateExistingPivot($farmB, ['buying_price_cents' => 275]);

        // Reorder (farm B becomes primary): the price must survive.
        $product->syncProducers([$farmB, $farmA]);
        $this->assertSame(275, (int) DB::table('product_producer')
            ->where('product_id', $product->id)->where('producer_id', $farmB)
            ->value('buying_price_cents'));

        // Detach and re-add: the price is gone (row recreated).
        $product->syncProducers([$farmA]);
        $product->syncProducers([$farmA, $farmB]);
        $this->assertNull(DB::table('product_producer')
            ->where('product_id', $product->id)->where('producer_id', $farmB)
            ->value('buying_price_cents'));
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
