<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use App\Models\ProducerTranslation;
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
            'image_url' => 'https://example.com/producers/sonnenfeld.jpg',
        ]);

        $this->assertDatabaseHas('producers', [
            'id' => $producer->id,
            'image_url' => 'https://example.com/producers/sonnenfeld.jpg',
        ]);

        foreach (['de', 'en', 'fr'] as $locale) {
            ProducerTranslation::query()->create([
                'producer_id' => $producer->id,
                'locale' => $locale,
                'name' => 'Hof Sonnenfeld',
                'region' => 'Bayern',
                'description' => 'Familienbetrieb mit saisonalem Gemüse.',
            ]);
        }

        ProducerTranslation::query()
            ->where('producer_id', $producer->id)
            ->where('locale', 'de')
            ->update(['region' => 'Hessen']);

        $this->assertDatabaseHas('producer_translations', [
            'producer_id' => $producer->id,
            'locale' => 'de',
            'region' => 'Hessen',
        ]);

        $producer->delete();

        $this->assertDatabaseMissing('producers', [
            'id' => $producer->id,
        ]);

        $this->assertDatabaseMissing('producer_translations', [
            'producer_id' => $producer->id,
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
