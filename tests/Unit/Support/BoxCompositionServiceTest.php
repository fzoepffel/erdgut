<?php

namespace Tests\Unit\Support;

use App\Models\Box;
use App\Models\Product;
use App\Support\BoxCompositionService;
use App\Support\BoxValueRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BoxCompositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoxCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoxCompositionService(new BoxValueRecalculationService);
    }

    public function test_compose_fills_box_with_products_per_size_rules(): void
    {
        Config::set('box.composition_by_size.small', [
            'vegetables' => 2,
            'fruit' => 1,
        ]);

        Product::factory()->count(5)->create(['category' => 'vegetables', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'fruit', 'is_available' => true]);

        $box = Box::factory()->create(['estimated_value' => 0]);

        $added = $this->service->compose($box, 'small');

        $this->assertSame(3, $added);
        $this->assertSame(3, $box->items()->count());

        $categories = $box->items()->with('product')->get()->pluck('product.category')->sort()->values()->toArray();
        $this->assertContains('vegetables', $categories);
        $this->assertContains('fruit', $categories);
        $this->assertCount(2, array_filter($categories, fn ($c) => $c === 'vegetables'));
        $this->assertCount(1, array_filter($categories, fn ($c) => $c === 'fruit'));
    }

    public function test_compose_recalculates_estimated_value(): void
    {
        Config::set('box.composition_by_size.medium', [
            'dairy' => 1,
        ]);

        Product::factory()->create([
            'category' => 'dairy',
            'is_available' => true,
            'unit_price' => 4.50,
        ]);

        $box = Box::factory()->create(['estimated_value' => 0]);

        $this->service->compose($box, 'medium');

        $box->refresh();
        $this->assertEquals(4.50, (float) $box->estimated_value);
    }

    public function test_compose_skips_unavailable_products(): void
    {
        Config::set('box.composition_by_size.small', [
            'vegetables' => 3,
        ]);

        Product::factory()->count(2)->create(['category' => 'vegetables', 'is_available' => true]);
        Product::factory()->count(5)->create(['category' => 'vegetables', 'is_available' => false]);

        $box = Box::factory()->create();

        $added = $this->service->compose($box, 'small');

        $this->assertSame(2, $added);
        $this->assertSame(2, $box->items()->count());
    }

    public function test_compose_returns_zero_for_unknown_size(): void
    {
        $box = Box::factory()->create();

        $added = $this->service->compose($box, 'nonexistent');

        $this->assertSame(0, $added);
        $this->assertSame(0, $box->items()->count());
    }

    public function test_compose_sets_unit_from_product_metadata(): void
    {
        Config::set('box.composition_by_size.small', [
            'bakery' => 1,
        ]);

        Product::factory()->create([
            'category' => 'bakery',
            'is_available' => true,
            'metadata' => ['unit' => 'piece', 'origin' => 'local'],
        ]);

        $box = Box::factory()->create();

        $this->service->compose($box, 'small');

        $item = $box->items()->first();
        $this->assertSame('piece', $item->unit);
        $this->assertFalse($item->is_extra);
        $this->assertEquals(1, (float) $item->quantity);
    }

    public function test_different_sizes_produce_different_item_counts(): void
    {
        Product::factory()->count(10)->create(['category' => 'vegetables', 'is_available' => true]);
        Product::factory()->count(5)->create(['category' => 'fruit', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'dairy', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'bakery', 'is_available' => true]);

        $smallBox = Box::factory()->create();
        $largeBox = Box::factory()->create();

        $smallCount = $this->service->compose($smallBox, 'small');
        $largeCount = $this->service->compose($largeBox, 'large');

        $this->assertGreaterThan($smallCount, $largeCount);
    }

    public function test_compose_uses_default_unit_piece_when_metadata_has_no_unit(): void
    {
        Config::set('box.composition_by_size.small', [
            'herbs' => 1,
        ]);

        Product::factory()->create([
            'category' => 'herbs',
            'is_available' => true,
            'metadata' => ['origin' => 'local'],
        ]);

        $box = Box::factory()->create();

        $this->service->compose($box, 'small');

        $item = $box->items()->first();
        $this->assertSame('piece', $item->unit);
    }

    public function test_compose_does_not_recalculate_when_zero_items_added(): void
    {
        $box = Box::factory()->create(['estimated_value' => 99.99]);

        $added = $this->service->compose($box, 'nonexistent');

        $this->assertSame(0, $added);
        $box->refresh();
        $this->assertEquals(99.99, (float) $box->estimated_value);
    }

    public function test_compose_marks_all_items_as_not_extra(): void
    {
        Config::set('box.composition_by_size.medium', [
            'vegetables' => 2,
            'fruit' => 1,
        ]);

        Product::factory()->count(3)->create(['category' => 'vegetables', 'is_available' => true]);
        Product::factory()->count(2)->create(['category' => 'fruit', 'is_available' => true]);

        $box = Box::factory()->create();

        $this->service->compose($box, 'medium');

        $box->items()->get()->each(function ($item): void {
            $this->assertFalse($item->is_extra);
        });
    }
}
