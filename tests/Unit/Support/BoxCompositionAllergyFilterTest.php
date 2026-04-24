<?php

namespace Tests\Unit\Support;

use App\Enums\Allergen;
use App\Enums\DietaryTag;
use App\Models\Box;
use App\Models\Product;
use App\Support\BoxCompositionService;
use App\Support\BoxValueRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * ERD-347: auto-filter composition by user's allergies + dietary tags.
 *
 * These tests exercise the full path (snapshot -> compose -> pick) and
 * verify that products the subscriber opted out of never land in the box.
 */
class BoxCompositionAllergyFilterTest extends TestCase
{
    use RefreshDatabase;

    private BoxCompositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoxCompositionService(new BoxValueRecalculationService);
    }

    public function test_gluten_allergy_excludes_gluten_products(): void
    {
        Config::set('box.composition_by_size.small', ['bakery' => 3]);

        $gluten = Product::factory()->count(5)->create([
            'category' => 'bakery',
            'is_available' => true,
            'allergens' => [Allergen::Gluten->value],
        ]);
        $safe = Product::factory()->count(3)->create([
            'category' => 'bakery',
            'is_available' => true,
            'allergens' => [],
        ]);

        $box = Box::factory()->create([
            'estimated_value' => 0,
            'allergies_snapshot' => [Allergen::Gluten->value],
            'preferences_snapshot' => ['veg_only' => false, 'eggs_dairy_add_on' => true],
        ]);

        $added = $this->service->compose($box, 'small');

        $this->assertSame(3, $added);
        $pickedIds = $box->items()->pluck('product_id')->all();
        $this->assertEmpty(array_intersect($pickedIds, $gluten->pluck('id')->all()),
            'No gluten-tagged product should be composed into a gluten-allergic box.');
        $this->assertNotEmpty(array_intersect($pickedIds, $safe->pluck('id')->all()));
    }

    public function test_vegetarian_pref_excludes_meat_and_fish_products(): void
    {
        Config::set('box.composition_by_size.medium', ['mains' => 2]);

        $meat = Product::factory()->count(3)->create([
            'category' => 'mains',
            'is_available' => true,
            'dietary_tags' => [DietaryTag::ContainsMeat->value],
        ]);
        $fish = Product::factory()->count(2)->create([
            'category' => 'mains',
            'is_available' => true,
            'dietary_tags' => [DietaryTag::ContainsFish->value],
        ]);
        $veg = Product::factory()->count(4)->create([
            'category' => 'mains',
            'is_available' => true,
            'dietary_tags' => [DietaryTag::Vegetarian->value],
        ]);

        $box = Box::factory()->create([
            'estimated_value' => 0,
            'allergies_snapshot' => [],
            'preferences_snapshot' => ['veg_only' => true, 'eggs_dairy_add_on' => true],
        ]);

        $this->service->compose($box, 'medium');

        $pickedIds = $box->items()->pluck('product_id')->all();
        $this->assertEmpty(array_intersect($pickedIds, $meat->pluck('id')->all()));
        $this->assertEmpty(array_intersect($pickedIds, $fish->pluck('id')->all()));
        $this->assertNotEmpty(array_intersect($pickedIds, $veg->pluck('id')->all()));
    }

    public function test_eggs_dairy_off_excludes_eggs_and_dairy_products(): void
    {
        Config::set('box.composition_by_size.medium', ['dairy' => 2]);

        $eggs = Product::factory()->count(3)->create([
            'category' => 'dairy',
            'is_available' => true,
            'dietary_tags' => [DietaryTag::ContainsEggs->value],
        ]);
        $milk = Product::factory()->count(3)->create([
            'category' => 'dairy',
            'is_available' => true,
            'dietary_tags' => [DietaryTag::ContainsDairy->value],
        ]);
        $plantBased = Product::factory()->count(3)->create([
            'category' => 'dairy',
            'is_available' => true,
            'dietary_tags' => [DietaryTag::Vegan->value],
        ]);

        $box = Box::factory()->create([
            'estimated_value' => 0,
            'allergies_snapshot' => [],
            'preferences_snapshot' => ['veg_only' => false, 'eggs_dairy_add_on' => false],
        ]);

        $this->service->compose($box, 'medium');

        $pickedIds = $box->items()->pluck('product_id')->all();
        $this->assertEmpty(array_intersect($pickedIds, $eggs->pluck('id')->all()));
        $this->assertEmpty(array_intersect($pickedIds, $milk->pluck('id')->all()));
    }

    public function test_multiple_allergens_stack(): void
    {
        Config::set('box.composition_by_size.small', ['pantry' => 2]);

        $peanut = Product::factory()->count(2)->create([
            'category' => 'pantry',
            'is_available' => true,
            'allergens' => [Allergen::Peanuts->value],
        ]);
        $nutty = Product::factory()->count(2)->create([
            'category' => 'pantry',
            'is_available' => true,
            'allergens' => [Allergen::Nuts->value],
        ]);
        $safe = Product::factory()->count(3)->create([
            'category' => 'pantry',
            'is_available' => true,
            'allergens' => [],
        ]);

        $box = Box::factory()->create([
            'estimated_value' => 0,
            'allergies_snapshot' => [Allergen::Peanuts->value, Allergen::Nuts->value],
            'preferences_snapshot' => ['veg_only' => false, 'eggs_dairy_add_on' => true],
        ]);

        $this->service->compose($box, 'small');

        $pickedIds = $box->items()->pluck('product_id')->all();
        $this->assertEmpty(array_intersect($pickedIds, $peanut->pluck('id')->all()));
        $this->assertEmpty(array_intersect($pickedIds, $nutty->pluck('id')->all()));
        $this->assertNotEmpty(array_intersect($pickedIds, $safe->pluck('id')->all()));
    }
}
