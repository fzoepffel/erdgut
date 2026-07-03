<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The admin shop-orders overview is THE order list: B2C shop orders and firm
 * B2B restaurant orders side by side; pending requests stay in the Anfragen
 * queue (linked with an open count) until granted.
 */
class AdminUnifiedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function stockedProduct(float $qty = 100): Product
    {
        Producer::factory()->create();
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 2.0,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => Carbon::tomorrow()->toDateString(),
            'quantity' => $qty,
            'freshness_window_days' => 3,
        ]);

        return $product;
    }

    private function placeRestaurantOrder(Product $product, float $qty = 5): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        return app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $qty]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'Teststr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
        ]);
    }

    private function submitRequest(Product $product): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        return app(RestaurantOrderService::class)->submitRequest($profile->user, [
            'items' => [['name' => 'Wildkräuter', 'unit' => 'kg', 'quantity' => 2]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'Teststr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
        ]);
    }

    public function test_unified_index_shows_confirmed_restaurant_orders_with_badge(): void
    {
        $order = $this->placeRestaurantOrder($this->stockedProduct());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-restaurant-row-'.$order->id.'"', false)
            ->assertSee(__('shop.admin.badge_restaurant'))
            ->assertSee(route('app.admin.restaurant-orders.show', $order), false);
    }

    public function test_pending_requests_stay_out_but_show_in_the_header_badge(): void
    {
        $request = $this->submitRequest($this->stockedProduct());
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            // Not a row in the list…
            ->assertDontSee('data-testid="shop-admin-restaurant-row-'.$request->id.'"', false)
            // …but the Anfragen link carries the open count.
            ->assertSee('data-testid="shop-admin-requests-link"', false)
            ->assertSee(route('app.admin.restaurant-orders.index', ['kind' => 'request', 'status' => 'submitted'])); // escaped: & -> &amp;

        $this->assertStringContainsString('>1</span>', $response->getContent());
    }

    public function test_granted_request_is_pushed_into_the_unified_list(): void
    {
        $request = $this->submitRequest($this->stockedProduct());
        $svc = app(RestaurantOrderService::class);
        foreach ($request->items as $item) {
            $svc->acceptLine($item);
        }
        $this->assertSame('confirmed', $request->refresh()->status->value);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-restaurant-row-'.$request->id.'"', false)
            ->assertSee(__('restaurant.kind_request'));
    }

    public function test_type_filter_isolates_restaurant_orders(): void
    {
        $order = $this->placeRestaurantOrder($this->stockedProduct());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['type' => 'b2b-restaurant']))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-restaurant-row-'.$order->id.'"', false);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['type' => 'b2c']))
            ->assertOk()
            ->assertDontSee('data-testid="shop-admin-restaurant-row-'.$order->id.'"', false);
    }

    public function test_default_view_focuses_the_next_delivery_day(): void
    {
        $product = $this->stockedProduct();
        $tomorrow = $this->placeRestaurantOrder($product);
        // A later order on another day.
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => Carbon::tomorrow()->addDays(3)->toDateString(),
            'quantity' => 50,
            'freshness_window_days' => 3,
        ]);
        $profile = RestaurantProfile::factory()->verified()->create();
        $later = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
            'expected_delivery_date' => Carbon::tomorrow()->addDays(3)->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        $admin = User::factory()->admin()->create();

        // No date params → focused on the NEXT delivery day only.
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index'))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-restaurant-row-'.$tomorrow->id.'"', false)
            ->assertDontSee('data-testid="shop-admin-restaurant-row-'.$later->id.'"', false)
            ->assertSee('data-testid="shop-admin-calendar"', false)
            ->assertSee('data-testid="shop-admin-all-dates"', false);

        // Explicitly cleared dates → all orders.
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['delivery_from' => '', 'delivery_to' => '']))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-restaurant-row-'.$tomorrow->id.'"', false)
            ->assertSee('data-testid="shop-admin-restaurant-row-'.$later->id.'"', false);
    }

    public function test_weekly_standing_order_shows_every_week_in_the_list(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        $product = $this->stockedProduct(1000);
        $profile = RestaurantProfile::factory()->verified()->create();
        // Weekly line via the shop route → the standing order activates
        // instantly and the whole horizon materializes.
        $this->actingAs($profile->user)->post(route('app.restaurant.orders.store'), [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3, 'recurrence' => 'weekly']],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ])->assertRedirect();
        $standing = RestaurantStandingOrder::firstOrFail();

        $weeks = RestaurantOrder::where('source_standing_order_id', $standing->id)->count();
        $this->assertGreaterThanOrEqual(5, $weeks); // origin + horizon weeks

        $admin = User::factory()->admin()->create();
        $html = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['delivery_from' => '', 'delivery_to' => '', 'type' => 'b2b-restaurant']))
            ->assertOk()
            ->getContent();
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'data-testid="shop-admin-restaurant-row-'));
        Carbon::setTestNow(null);
    }

    public function test_packing_includes_restaurant_lines_with_b2b_tag_and_toggle(): void
    {
        $product = $this->stockedProduct();
        $order = $this->placeRestaurantOrder($product);
        $item = $order->items()->firstOrFail();
        $admin = User::factory()->admin()->create();

        // The restaurant line appears on the packing screen (namespaced token).
        $this->actingAs($admin)
            ->get(route('app.admin.packing.index', ['date' => Carbon::tomorrow()->toDateString()]))
            ->assertOk()
            ->assertSee('r'.$item->id, false)
            ->assertSee(__('shop.admin.badge_b2b'));

        // Toggle packs it…
        $this->actingAs($admin)
            ->post(route('app.admin.packing.toggle', ['item' => 'r'.$item->id]))
            ->assertOk()
            ->assertJsonPath('packed', true)
            ->assertJsonPath('order.key', 'r'.$order->id)
            ->assertJsonPath('order.ready', true);
        $this->assertNotNull($item->refresh()->packed_at);

        // …and pack-product covers restaurant lines too (after unpacking).
        $this->actingAs($admin)->post(route('app.admin.packing.toggle', ['item' => 'r'.$item->id]))->assertOk();
        $this->assertNull($item->refresh()->packed_at);
        $this->actingAs($admin)
            ->post(route('app.admin.packing.product'), [
                'date' => Carbon::tomorrow()->toDateString(),
                'product_id' => $product->id,
                'variant_id' => null,
            ])
            ->assertOk();
        $this->assertNotNull($item->refresh()->packed_at);
    }
}
