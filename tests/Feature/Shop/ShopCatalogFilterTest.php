<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductSubcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));

        ProductSubcategory::query()->create([
            'category' => 'produce',
            'label_de' => 'Wurzeln & Knollen',
            'label_en' => 'Roots & Tubers',
            'label_fr' => 'Racines & Tubercules',
            'sort_order' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stockProduct(string $category = 'produce'): Product
    {
        $product = Product::factory()->create([
            'category' => $category,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 10,
            'freshness_window_days' => 1,
        ]);

        return $product;
    }

    public function test_single_category_day_preselects_category_and_shows_subcategory_rail(): void
    {
        $this->stockProduct('produce');

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee('data-testid="shop-name-filter"', false)
            ->assertSee(__('shop.grid_filter_category_label'), false)
            ->assertSee(__('shop.grid_filter_subcategory_label'), false)
            ->assertSee("defaultShopCategory: 'produce'", false)
            ->assertSee("activeCategory: config.defaultShopCategory || ''", false);
    }

    public function test_multi_category_day_starts_with_no_active_category(): void
    {
        $this->stockProduct('produce');
        $this->stockProduct('meat');

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee(__('shop.grid_filter_category_label'), false)
            ->assertSee("defaultShopCategory: ''", false);
    }
}
