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
            ->assertSee(__('shop.admin.badge_b2b'))
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
            ->assertSee(route('app.admin.restaurant-orders.index', ['status' => 'submitted']), false);

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

    public function test_requests_queue_excludes_firm_orders(): void
    {
        $product = $this->stockedProduct();
        $order = $this->placeRestaurantOrder($product);   // firm order
        $request = $this->submitRequest($product);         // pending request
        $admin = User::factory()->admin()->create();

        // The Anfragen queue lists ONLY requests — firm orders live in the
        // unified shop-orders overview.
        $html = $this->actingAs($admin)
            ->get(route('app.admin.restaurant-orders.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(route('app.admin.restaurant-orders.show', $request), $html);
        $this->assertStringNotContainsString(route('app.admin.restaurant-orders.show', $order), $html);
    }

    public function test_type_filter_isolates_restaurant_orders(): void
    {
        $order = $this->placeRestaurantOrder($this->stockedProduct());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', ['type' => 'b2b']))
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
            ->get(route('app.admin.shop-orders.index', ['delivery_from' => '', 'delivery_to' => '', 'type' => 'b2b']))
            ->assertOk()
            ->getContent();
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'data-testid="shop-admin-restaurant-row-'));
        Carbon::setTestNow(null);
    }

    public function test_admin_creates_recurring_b2b_delivery_via_create_form(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
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
            'quantity' => 500,
            'freshness_window_days' => 60,
        ]);
        $profile = RestaurantProfile::factory()->verified()->create();
        $admin = User::factory()->admin()->create();

        $payload = [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5, 'line_total_eur' => 10, 'vat_rate_percent' => 7]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
            'delivery_fee_eur' => 0, 'delivery_fee_vat_rate_percent' => 7,
            'customer_type' => 'business',
            'recurring' => 1,
            'restaurant_user_id' => $profile->user_id,
        ];

        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // A restaurant order + an ACTIVE standing order + materialized weeks.
        $standing = RestaurantStandingOrder::firstOrFail();
        $this->assertTrue($standing->isActive());
        $this->assertSame($profile->user_id, $standing->user_id);
        $weeks = RestaurantOrder::where('source_standing_order_id', $standing->id)->count();
        $this->assertGreaterThanOrEqual(5, $weeks); // origin + horizon

        // Guards: private customer or missing account → validation error.
        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.store'), array_merge($payload, ['customer_type' => 'private']))
            ->assertSessionHasErrors('recurring');
        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.store'), array_merge($payload, ['restaurant_user_id' => null]))
            ->assertSessionHasErrors('restaurant_user_id');
        Carbon::setTestNow(null);
    }

    public function test_recurring_without_account_auto_creates_a_verified_one(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
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
            'quantity' => 500,
            'freshness_window_days' => 60,
        ]);
        $admin = User::factory()->admin()->create();

        $payload = [
            'name' => 'Trattoria Nuova',
            'email' => 'chef@trattoria-nuova.example',
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5, 'line_total_eur' => 10, 'vat_rate_percent' => 7]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Via Roma 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'delivery_fee_eur' => 0, 'delivery_fee_vat_rate_percent' => 7,
            'customer_type' => 'business',
            'recurring' => 1,
        ];

        // Missing contact → validation error (no silent account creation).
        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.store'), array_merge($payload, ['email' => '']))
            ->assertSessionHasErrors('restaurant_user_id');

        // With name + email → account auto-created, verified, standing active.
        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::where('email', 'chef@trattoria-nuova.example')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->restaurantProfile->isVerified());
        $this->assertSame('Trattoria Nuova', $user->restaurantProfile->business_name);
        $this->assertSame('Via Roma 1', $user->restaurantProfile->billing_street);

        $standing = RestaurantStandingOrder::firstOrFail();
        $this->assertTrue($standing->isActive());
        $this->assertSame($user->id, $standing->user_id);
        $this->assertGreaterThanOrEqual(5, RestaurantOrder::where('source_standing_order_id', $standing->id)->count());

        // Same email again → the existing account is reused, not duplicated.
        $this->actingAs($admin)
            ->post(route('app.admin.shop-orders.store'), array_merge($payload, [
                'expected_delivery_date' => Carbon::tomorrow()->addDay()->toDateString(),
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('email', 'chef@trattoria-nuova.example')->count());
        Carbon::setTestNow(null);
    }

    public function test_detail_page_presentation_follows_status(): void
    {
        $product = $this->stockedProduct();
        $request = $this->submitRequest($product);
        $admin = User::factory()->admin()->create();

        // Pending request → review panel with per-line accept/decline; no
        // firm-order actions bar, no pricing section yet (not invoiceable).
        $item = $request->items()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('app.admin.restaurant-orders.show', $request))
            ->assertOk()
            ->assertSee('data-testid="restaurant-request-review"', false)
            ->assertSee('data-testid="restaurant-line-accept-'.$item->id.'"', false)
            ->assertDontSee('data-testid="restaurant-order-actions"', false)
            ->assertDontSee('data-testid="restaurant-order-fulfil"', false);

        // Granted → the SAME page turns into a normal order detail: actions
        // bar with fulfil, read-only items, collapsed adjust section — and no
        // review panel anymore.
        app(RestaurantOrderService::class)->acceptLine($item);
        $this->assertSame('confirmed', $request->refresh()->status->value);
        $this->actingAs($admin)
            ->get(route('app.admin.restaurant-orders.show', $request))
            ->assertOk()
            ->assertSee('data-testid="restaurant-order-actions"', false)
            ->assertSee('data-testid="restaurant-order-fulfil"', false)
            ->assertSee('data-testid="restaurant-order-items"', false)
            ->assertSee('data-testid="restaurant-order-adjust-toggle"', false)
            ->assertSee('data-testid="restaurant-order-issue-invoice"', false)
            ->assertDontSee('data-testid="restaurant-request-review"', false)
            ->assertDontSee('data-testid="restaurant-line-accept-'.$item->id.'"', false);
    }

    public function test_calendar_month_endpoint_serves_any_month(): void
    {
        $order = $this->placeRestaurantOrder($this->stockedProduct());
        $admin = User::factory()->admin()->create();
        $month = Carbon::tomorrow()->format('Y-m');

        $days = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.calendar-month', ['month' => $month]))
            ->assertOk()
            ->json('days');
        $day = collect($days)->firstWhere('iso', Carbon::tomorrow()->toDateString());
        $this->assertSame(1, $day['orders']);

        // A far-past month works too (orders only, stock skipped).
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.calendar-month', ['month' => '2025-01']))
            ->assertOk()
            ->assertJsonCount(31, 'days');

        // Malformed month → 422; non-admin → forbidden.
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.calendar-month', ['month' => 'nope']))
            ->assertStatus(422);
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.shop-orders.calendar-month', ['month' => $month]))
            ->assertForbidden();
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
