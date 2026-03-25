<?php

namespace Tests\Unit\Services;

use App\Models\Producer;
use App\Models\Product;
use App\Services\Admin\CanonicalBoxTemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CanonicalBoxTemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_respects_cap_using_cheapest_first(): void
    {
        Config::set('box.composition_by_size.small', [
            'vegetables' => 2,
        ]);

        $producer = Producer::factory()->create();

        Product::factory()->for($producer)->create([
            'category' => 'vegetables',
            'unit_price' => 3.00,
            'is_available' => true,
            'is_published' => true,
        ]);
        Product::factory()->for($producer)->create([
            'category' => 'vegetables',
            'unit_price' => 2.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        $builder = app(CanonicalBoxTemplateBuilder::class);
        $result = $builder->build('small', 5.0);

        $this->assertCount(2, $result['lines']);
        $this->assertSame(5.0, $result['total']);
        $this->assertSame(2.0, $result['lines'][0]['line_total']);
        $this->assertSame(3.0, $result['lines'][1]['line_total']);
    }

    public function test_build_returns_empty_when_cap_zero(): void
    {
        Config::set('box.composition_by_size.small', [
            'vegetables' => 1,
        ]);

        Product::factory()->create([
            'category' => 'vegetables',
            'unit_price' => 1.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        $builder = app(CanonicalBoxTemplateBuilder::class);
        $result = $builder->build('small', 0.0);

        $this->assertSame([], $result['lines']);
        $this->assertSame(0.0, $result['total']);
    }
}
