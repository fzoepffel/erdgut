<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_url_is_null_when_image_path_is_null(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create(['image_path' => null]);

        $this->assertNull($product->imageUrl());
    }

    public function test_image_url_points_to_public_disk_when_path_set(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/test.jpg', 'fake-image');

        $product = Product::factory()->create(['image_path' => 'products/test.jpg']);

        $url = $product->imageUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/products/test.jpg', $url);
    }
}
