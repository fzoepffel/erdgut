<?php

namespace Tests\Unit\Models;

use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProducerPrimaryImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_image_url_prefers_uploaded_path_over_external(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('producers/1/card.jpg', 'fake');

        $producer = Producer::factory()->create([
            'image_path' => 'producers/1/card.jpg',
            'image_url' => 'https://example.com/legacy.jpg',
        ]);

        $url = $producer->primaryImageUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/producers/1/card.jpg', $url);
    }

    public function test_primary_image_url_falls_back_to_image_url(): void
    {
        Storage::fake('public');

        $producer = Producer::factory()->create([
            'image_path' => null,
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertSame('https://example.com/photo.jpg', $producer->primaryImageUrl());
    }

    public function test_primary_image_url_is_null_without_both(): void
    {
        Storage::fake('public');

        $producer = Producer::factory()->create([
            'image_path' => null,
            'image_url' => null,
        ]);

        $this->assertNull($producer->primaryImageUrl());
    }

    public function test_primary_image_url_falls_back_to_image_url_when_uploaded_file_missing(): void
    {
        Storage::fake('public');

        $producer = Producer::factory()->create([
            'image_path' => 'producers/99/missing.jpg',
            'image_url' => 'https://example.com/fallback.jpg',
        ]);

        $this->assertSame('https://example.com/fallback.jpg', $producer->primaryImageUrl());
    }
}
