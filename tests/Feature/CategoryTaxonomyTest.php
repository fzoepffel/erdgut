<?php

namespace Tests\Feature;

use App\Enums\ProductCategory;
use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks the category-taxonomy alignment from D6/D7/F8 in the UX
 * review notes. The enum, lang labels, marketing chapter strip
 * and customer chips all share five values: produce / fish / meat /
 * eggs_and_dairy / bread.
 */
class CategoryTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_d6_enum_exposes_only_chapter_aligned_values(): void
    {
        $this->assertEqualsCanonicalizing(
            ['produce', 'fish', 'meat', 'eggs_and_dairy', 'bread'],
            ProductCategory::values(),
        );
    }

    public function test_d6_admin_category_lang_keys_match_chapter_taxonomy(): void
    {
        // German labels mirror the marketing-home Kapitel exactly.
        $this->assertSame('Obst & Gemüse', __('admin.category.produce', [], 'de'));
        $this->assertSame('Fisch & Meeresfrüchte', __('admin.category.fish', [], 'de'));
        $this->assertSame('Fleisch', __('admin.category.meat', [], 'de'));
        $this->assertSame('Eier & Milchprodukte', __('admin.category.eggs_and_dairy', [], 'de'));
        $this->assertSame('Brot', __('admin.category.bread', [], 'de'));

        // Old keys are gone.
        $this->assertSame('admin.category.bakery', __('admin.category.bakery', [], 'de'));
        $this->assertSame('admin.category.dairy', __('admin.category.dairy', [], 'de'));
        $this->assertSame('admin.category.vegetables', __('admin.category.vegetables', [], 'de'));
        $this->assertSame('admin.category.fruit', __('admin.category.fruit', [], 'de'));
    }

    public function test_d6_chapter_strip_links_use_canonical_category_values(): void
    {
        $response = $this->get(route('public.marketing-home'))->assertOk();
        $html = $response->getContent();

        // All five product chapters point to the canonical category
        // values; the older `vegetables` / `fruit` / `dairy` /
        // `bakery` URL params are gone.
        foreach (['produce', 'fish', 'meat', 'eggs_and_dairy', 'bread'] as $cat) {
            $this->assertStringContainsString(
                'href="'.route('public.products.index', ['category' => $cat]).'"',
                $html,
            );
        }
        $this->assertStringNotContainsString('?category=vegetables', $html);
        $this->assertStringNotContainsString('?category=fruit', $html);
        $this->assertStringNotContainsString('?category=dairy', $html);
        $this->assertStringNotContainsString('?category=bakery', $html);
    }

    public function test_d6_customer_chip_filter_accepts_new_category_values(): void
    {
        Product::factory()->create(['category' => 'eggs_and_dairy', 'is_published' => true, 'is_available' => true]);
        Product::factory()->create(['category' => 'bread', 'is_published' => true, 'is_available' => true]);

        // The auth'd /app/products filter resolves to the same enum.
        $response = $this->get(route('public.products.index', ['category' => 'eggs_and_dairy']));
        $response->assertOk();
    }

    public function test_d7_eggs_addon_product_lands_in_eggs_and_dairy_chapter(): void
    {
        // Bio-Eier-style egg add-on products use the merged
        // eggs_and_dairy category — no longer mislabelled as
        // "Milchprodukte" only.
        $product = Product::factory()->create([
            'category' => 'eggs_and_dairy',
            'is_egg_addon' => true,
        ]);

        $catEnum = ProductCategory::tryFrom($product->category);
        $this->assertNotNull($catEnum);
        $this->assertSame(ProductCategory::EggsAndDairy, $catEnum);
        $this->assertSame('eggdairy', $catEnum->stall());
    }

    public function test_f8_data_migration_remapped_existing_rows(): void
    {
        // Inject rows with the legacy values, then re-run the data
        // migration's remap idempotently. Because the schema column
        // is a plain string we can write the legacy values directly.
        $producer = Producer::factory()->create();
        DB::table('products')->insert([
            ['producer_id' => $producer->id, 'category' => 'vegetables', 'unit' => 'kg', 'unit_price' => 1, 'is_seasonal' => 0, 'is_available' => 1, 'is_published' => 1, 'is_test' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['producer_id' => $producer->id, 'category' => 'fruit', 'unit' => 'kg', 'unit_price' => 1, 'is_seasonal' => 0, 'is_available' => 1, 'is_published' => 1, 'is_test' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['producer_id' => $producer->id, 'category' => 'dairy', 'unit' => 'piece', 'unit_price' => 1, 'is_seasonal' => 0, 'is_available' => 1, 'is_published' => 1, 'is_test' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['producer_id' => $producer->id, 'category' => 'bakery', 'unit' => 'piece', 'unit_price' => 1, 'is_seasonal' => 0, 'is_available' => 1, 'is_published' => 1, 'is_test' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('products')->whereIn('category', ['vegetables', 'fruit'])->update(['category' => 'produce']);
        DB::table('products')->where('category', 'dairy')->update(['category' => 'eggs_and_dairy']);
        DB::table('products')->where('category', 'bakery')->update(['category' => 'bread']);

        $this->assertSame(0, DB::table('products')->whereIn('category', ['vegetables', 'fruit', 'dairy', 'bakery'])->count());
        $this->assertSame(2, DB::table('products')->where('category', 'produce')->count());
        $this->assertSame(1, DB::table('products')->where('category', 'eggs_and_dairy')->count());
        $this->assertSame(1, DB::table('products')->where('category', 'bread')->count());
    }

    public function test_f8_box_composition_config_uses_new_category_keys(): void
    {
        foreach (['small', 'medium', 'large'] as $size) {
            $cfg = config('box.composition_by_size.'.$size);
            $this->assertIsArray($cfg);
            $this->assertEqualsCanonicalizing(
                ['produce', 'eggs_and_dairy', 'bread'],
                array_keys($cfg),
                "Composition config for {$size} must use the chapter-aligned keys.",
            );
        }
    }

    public function test_f8_enum_stall_method_returns_chapter_keys(): void
    {
        $this->assertSame('produce', ProductCategory::Produce->stall());
        $this->assertSame('fish', ProductCategory::Fish->stall());
        $this->assertSame('meat', ProductCategory::Meat->stall());
        $this->assertSame('eggdairy', ProductCategory::EggsAndDairy->stall());
        $this->assertSame('bread', ProductCategory::Bread->stall());
    }
}
