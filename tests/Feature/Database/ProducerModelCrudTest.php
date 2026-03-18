<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerModelCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_can_be_created_updated_and_deleted(): void
    {
        $producer = Producer::create([
            'name' => 'Hof Sonnenfeld',
            'region' => 'Bayern',
            'description' => 'Familienbetrieb mit saisonalem Gemuese.',
            'image_url' => 'https://example.com/producers/sonnenfeld.jpg',
        ]);

        $this->assertDatabaseHas('producers', [
            'id' => $producer->id,
            'name' => 'Hof Sonnenfeld',
            'region' => 'Bayern',
        ]);

        $producer->update([
            'region' => 'Hessen',
        ]);

        $this->assertDatabaseHas('producers', [
            'id' => $producer->id,
            'region' => 'Hessen',
        ]);

        $producer->delete();

        $this->assertDatabaseMissing('producers', [
            'id' => $producer->id,
        ]);
    }

    public function test_producer_products_relationship_points_to_product_model(): void
    {
        $producer = Producer::factory()->create();

        $relation = $producer->products();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame(Product::class, $relation->getRelated()::class);
    }
}
