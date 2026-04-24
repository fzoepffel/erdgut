<?php

namespace Tests\Unit\Support;

use App\Enums\DietaryTag;
use App\Models\Product;
use App\Support\WeeklyDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDistributionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WeeklyDistributionService
    {
        return new WeeklyDistributionService;
    }

    public function test_pick_products_excludes_products_tagged_with_a_listed_allergen(): void
    {
        Product::factory()->create([
            'category' => 'bakery',
            'allergens' => ['gluten'],
        ]);
        $safe = Product::factory()->create([
            'category' => 'bakery',
            'allergens' => ['sesame'],
        ]);

        $picks = $this->service()->pickProducts('bakery', 10, ['gluten'], []);

        $this->assertCount(1, $picks);
        $this->assertSame($safe->id, $picks->first()->id);
    }

    public function test_veg_only_preference_excludes_meat_and_fish(): void
    {
        Product::factory()->create([
            'category' => 'protein',
            'dietary_tags' => [DietaryTag::ContainsMeat->value],
        ]);
        Product::factory()->create([
            'category' => 'protein',
            'dietary_tags' => [DietaryTag::ContainsFish->value],
        ]);
        $tofu = Product::factory()->create([
            'category' => 'protein',
            'dietary_tags' => [DietaryTag::Vegan->value],
        ]);

        $picks = $this->service()->pickProducts('protein', 10, [], ['veg_only' => true]);

        $this->assertCount(1, $picks);
        $this->assertSame($tofu->id, $picks->first()->id);
    }

    public function test_eggs_dairy_add_on_default_excludes_eggs_and_dairy(): void
    {
        Product::factory()->create([
            'category' => 'dairy',
            'dietary_tags' => [DietaryTag::ContainsDairy->value],
        ]);
        Product::factory()->create([
            'category' => 'dairy',
            'dietary_tags' => [DietaryTag::ContainsEggs->value],
        ]);
        $oatMilk = Product::factory()->create([
            'category' => 'dairy',
            'dietary_tags' => [DietaryTag::Vegan->value],
        ]);

        $picks = $this->service()->pickProducts('dairy', 10, [], []);

        $this->assertCount(1, $picks);
        $this->assertSame($oatMilk->id, $picks->first()->id);
    }

    public function test_eggs_dairy_add_on_enabled_includes_eggs_and_dairy(): void
    {
        Product::factory()->count(2)->create([
            'category' => 'dairy',
            'dietary_tags' => [DietaryTag::ContainsDairy->value],
        ]);
        Product::factory()->create([
            'category' => 'dairy',
            'dietary_tags' => [DietaryTag::ContainsEggs->value],
        ]);

        $picks = $this->service()->pickProducts('dairy', 10, [], ['eggs_dairy_add_on' => true]);

        $this->assertCount(3, $picks);
    }

    public function test_limit_caps_the_selection(): void
    {
        Product::factory()->count(5)->create(['category' => 'vegetables']);

        $picks = $this->service()->pickProducts('vegetables', 2, [], []);

        $this->assertCount(2, $picks);
    }

    public function test_returns_empty_when_limit_is_zero(): void
    {
        Product::factory()->count(3)->create(['category' => 'vegetables']);

        $picks = $this->service()->pickProducts('vegetables', 0, [], []);

        $this->assertCount(0, $picks);
    }

    public function test_unpublished_or_unavailable_products_are_not_selected(): void
    {
        Product::factory()->create([
            'category' => 'fruit',
            'is_available' => false,
        ]);
        Product::factory()->create([
            'category' => 'fruit',
            'is_published' => false,
        ]);
        $visible = Product::factory()->create([
            'category' => 'fruit',
        ]);

        $picks = $this->service()->pickProducts('fruit', 10, [], []);

        $this->assertCount(1, $picks);
        $this->assertSame($visible->id, $picks->first()->id);
    }

    public function test_conflicts_returns_true_when_allergen_overlaps(): void
    {
        $product = Product::factory()->make(['allergens' => ['gluten', 'eggs']]);

        $this->assertTrue($this->service()->conflicts($product, ['eggs'], []));
        $this->assertFalse($this->service()->conflicts($product, ['fish'], []));
    }
}
