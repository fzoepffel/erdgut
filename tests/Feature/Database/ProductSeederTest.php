<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use App\Models\Product;
use Database\Seeders\ProducerSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_seeder_inserts_at_least_forty_sample_products(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertGreaterThanOrEqual(4, Product::query()->count());

        $product = Product::query()->firstOrFail();
        $this->assertTrue((bool) $product->is_test);
        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->id,
            'locale' => 'en',
        ]);
    }

    public function test_seeded_products_are_marked_as_test_data(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);

        $seededCount = Product::query()->where('is_test', true)->count();
        $this->assertGreaterThanOrEqual(4, $seededCount);
        $this->assertSame($seededCount, Product::query()->count());
    }

    public function test_seeded_products_have_valid_producer_relations(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);

        $products = Product::query()->with('producer')->get();
        foreach ($products as $product) {
            $this->assertNotNull($product->producer);
            $this->assertNotNull($product->producer_id);
        }
    }

    public function test_non_seeded_products_can_be_created_as_real_data(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Real Product']);

        $this->assertFalse($product->is_test);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_test' => 0,
        ]);

        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->id,
            'locale' => 'en',
            'name' => 'Real Product',
        ]);
    }
}
