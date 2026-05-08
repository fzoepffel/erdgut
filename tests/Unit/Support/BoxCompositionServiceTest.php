<?php

namespace Tests\Unit\Support;

use App\Models\Box;
use App\Models\BoxItem;
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
        // D6/D7/F8 (UX-review notes): the older vegetables + fruit
        // pairing collapsed into a single produce enum value, so the
        // multi-category test now mixes produce + bread instead.
        Config::set('box.composition_by_size.small', [
            'produce' => 2,
            'bread' => 1,
        ]);

        Product::factory()->count(5)->create(['category' => 'produce', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'bread', 'is_available' => true]);

        $box = Box::factory()->create(['estimated_value' => 0]);

        $added = $this->service->compose($box, 'small');

        $this->assertSame(3, $added);
        $this->assertSame(3, $box->items()->count());

        $categories = $box->items()->with('product')->get()->pluck('product.category')->sort()->values()->toArray();
        $this->assertContains('produce', $categories);
        $this->assertContains('bread', $categories);
        $this->assertCount(2, array_filter($categories, fn ($c) => $c === 'produce'));
        $this->assertCount(1, array_filter($categories, fn ($c) => $c === 'bread'));
    }

    public function test_compose_recalculates_estimated_value(): void
    {
        Config::set('box.composition_by_size.medium', [
            'eggs_and_dairy' => 1,
        ]);

        Product::factory()->create([
            'category' => 'eggs_and_dairy',
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
            'produce' => 3,
        ]);

        Product::factory()->count(2)->create(['category' => 'produce', 'is_available' => true]);
        Product::factory()->count(5)->create(['category' => 'produce', 'is_available' => false]);

        $box = Box::factory()->create();

        $added = $this->service->compose($box, 'small');

        $this->assertSame(2, $added);
        $this->assertSame(2, $box->items()->count());
    }

    public function test_compose_skips_unpublished_products(): void
    {
        Config::set('box.composition_by_size.small', [
            'produce' => 2,
        ]);

        Product::factory()->count(2)->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
        ]);
        Product::factory()->count(4)->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => false,
        ]);

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
            'bread' => 1,
        ]);

        Product::factory()->create([
            'category' => 'bread',
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
        Product::factory()->count(10)->create(['category' => 'produce', 'is_available' => true]);
        Product::factory()->count(5)->create(['category' => 'produce', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'eggs_and_dairy', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'bread', 'is_available' => true]);

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
            'produce' => 2,
            'bread' => 1,
        ]);

        Product::factory()->count(3)->create(['category' => 'produce', 'is_available' => true]);
        Product::factory()->count(2)->create(['category' => 'bread', 'is_available' => true]);

        $box = Box::factory()->create();

        $this->service->compose($box, 'medium');

        $box->items()->get()->each(function ($item): void {
            $this->assertFalse($item->is_extra);
        });
    }

    public function test_compose_preset_from_scratch_removes_core_lines_then_fills_and_keeps_extras(): void
    {
        Config::set('box.composition_by_size.small', [
            'produce' => 2,
            'bread' => 1,
        ]);

        Product::factory()->count(5)->create(['category' => 'produce', 'is_available' => true]);
        Product::factory()->count(3)->create(['category' => 'bread', 'is_available' => true]);
        $extraProduct = Product::factory()->create(['category' => 'eggs_and_dairy', 'is_available' => true]);

        $box = Box::factory()->create(['estimated_value' => 0]);

        $this->service->compose($box, 'small');
        $this->assertSame(3, $box->items()->count());

        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $extraProduct->id,
            'quantity' => 1,
            'unit' => 'piece',
            'is_extra' => true,
        ]);

        $this->assertSame(4, $box->items()->count());

        $added = $this->service->composePresetFromScratch($box, 'small');

        $this->assertSame(3, $added);
        $this->assertSame(4, $box->items()->count());
        $this->assertSame(1, $box->items()->where('is_extra', true)->count());
        $this->assertSame(3, $box->items()->where('is_extra', false)->count());
    }
}
