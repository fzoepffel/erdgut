<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_persists_required_attributes_and_links_to_producer(): void
    {
        $producer = Producer::factory()->create();

        $product = Product::create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit_price' => 3.49,
            'is_seasonal' => true,
            'is_available' => true,
            'metadata' => null,
        ]);

        foreach (['de', 'en', 'fr'] as $locale) {
            ProductTranslation::query()->create([
                'product_id' => $product->id,
                'locale' => $locale,
                'name' => 'Bio Karotten',
                'unit' => 'kg',
                'origin' => 'Bayern',
                'variety' => '—',
                'taste_profile' => 'Süß und knackig.',
            ]);
        }

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit_price' => 3.49,
            'is_seasonal' => 1,
            'is_available' => 1,
        ]);

        $this->assertSame($producer->id, $product->producer->id);
        $this->assertSame('Bio Karotten', $product->fresh()->name);
    }

    public function test_product_producer_relationship_is_belongs_to(): void
    {
        $relation = (new Product)->producer();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame(Producer::class, $relation->getRelated()::class);
    }
}
