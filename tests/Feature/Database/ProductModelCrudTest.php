<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use App\Models\Product;
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
            'name' => 'Bio Karotten',
            'category' => 'vegetables',
            'unit_price' => 3.49,
            'is_seasonal' => true,
            'is_available' => true,
            'metadata' => [
                'unit' => 'kg',
                'origin' => 'Bayern',
            ],
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'producer_id' => $producer->id,
            'name' => 'Bio Karotten',
            'category' => 'vegetables',
            'unit_price' => 3.49,
            'is_seasonal' => 1,
            'is_available' => 1,
        ]);

        $this->assertSame($producer->id, $product->producer->id);
        $this->assertSame('kg', $product->metadata['unit']);
    }

    public function test_product_producer_relationship_is_belongs_to(): void
    {
        $relation = (new Product)->producer();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame(Producer::class, $relation->getRelated()::class);
    }
}
