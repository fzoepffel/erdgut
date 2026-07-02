<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RestaurantCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function inStockProduct(Carbon $date, array $attrs = []): Product
    {
        Producer::factory()->create();
        $product = Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_seasonal' => false,
            'is_available' => true,
            'is_published' => true,
        ], $attrs));

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $date->toDateString(),
            'quantity' => 50,
            'freshness_window_days' => 3,
        ]);

        return $product;
    }

    /** Seasonal product with no stock → surfaces "on request" (season tab). */
    private function seasonalOnRequestProduct(): Product
    {
        Producer::factory()->create();

        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_seasonal' => true, // null window → always in season
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.restaurant.catalog'))->assertRedirect(route('auth.login'));
    }

    public function test_customer_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.restaurant.catalog'))
            ->assertForbidden();
    }

    public function test_pending_restaurant_is_redirected_to_pending(): void
    {
        $profile = RestaurantProfile::factory()->create();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertRedirect(route('app.restaurant.pending'));
    }

    public function test_verified_restaurant_sees_catalog_with_net_price_and_badge(): void
    {
        $this->app->setLocale('de');
        $date = Carbon::tomorrow();
        $product = $this->inStockProduct($date);
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('9,35'); // 10.00 gross - 7% VAT, net (de number format)
    }

    public function test_date_query_switches_in_stock_set(): void
    {
        $this->app->setLocale('de');
        $stockDay = Carbon::tomorrow()->addDay();
        $product = $this->inStockProduct($stockDay); // freshness 3d from stockDay
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        // On the stock day the product is in the shop grid.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog', ['date' => $stockDay->toDateString()]))
            ->assertOk()
            ->assertSee($product->name);

        // Tomorrow has NO stock — the shop's picker no longer offers it, so the
        // request falls back to the first stocked day (still showing the product).
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog', ['date' => Carbon::tomorrow()->toDateString()]))
            ->assertOk()
            ->assertSee($stockDay->locale('de')->isoFormat('dddd'))
            ->assertSee($product->name);
    }

    public function test_catalog_is_shop_only_with_nav_and_filters(): void
    {
        $this->app->setLocale('de');
        $this->inStockProduct(Carbon::tomorrow());
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertSee('data-testid="restaurant-nav"', false)
            ->assertSee('data-testid="restaurant-date-picker"', false)
            ->assertSee('data-testid="restaurant-name-filter"', false)
            // The shop has NO tabs anymore — buying only.
            ->assertDontSee('data-testid="restaurant-tabs"', false)
            ->assertSee(__('restaurant.cart_trust_line'))
            ->assertSee(route('app.restaurant.requests.index'), false)
            ->assertSee(route('app.restaurant.orders.index'), false)
            ->assertSee(route('app.restaurant.standing-orders.index'), false);
    }

    public function test_recurrence_weeks_carry_per_product_stock_for_the_until_picker(): void
    {
        $selected = Carbon::tomorrow();
        $product = $this->inStockProduct($selected); // stock only this week (freshness 3d)
        // Add stock for the SAME weekday next week too.
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $selected->copy()->addWeek()->toDateString(),
            'quantity' => 20, 'freshness_window_days' => 1,
        ]);

        $weeks = app(RestaurantCatalogService::class)->recurrenceWeeks($selected);
        $this->assertTrue($weeks->isNotEmpty());

        // Next week has the fresh inflow → in stock; a later week has none → 0 (amber).
        $nextWeek = $weeks->firstWhere('iso', $selected->copy()->addWeek()->toDateString());
        $this->assertNotNull($nextWeek);
        $this->assertEqualsWithDelta(20.0, $nextWeek['remainingByProduct'][$product->id], 0.01);

        $laterWeek = $weeks->firstWhere('iso', $selected->copy()->addWeeks(3)->toDateString());
        $this->assertNotNull($laterWeek);
        $this->assertSame(0.0, $laterWeek['remainingByProduct'][$product->id]);
    }

    public function test_catalog_ships_the_week_picker_and_over_stock_cue(): void
    {
        $this->app->setLocale('de');
        $this->inStockProduct(Carbon::tomorrow());
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertSee('recurrenceWeeks:', false)      // week data shipped to Alpine
            ->assertSee('restaurant-cap-hint', false)   // hard cap + handoff to Anfragen
            ->assertSee(__('restaurant.cap_request_link'))
            ->assertSee(__('restaurant.recurrence_open_ended'));
    }

    public function test_seasonal_product_with_stock_sells_in_the_shop_with_real_cap(): void
    {
        // Regression: a product that is seasonal AND in stock used to ship cap 0
        // (the old "seasonal = uncapped" rule), leaving an empty amount dropdown.
        $date = Carbon::tomorrow();
        $product = $this->inStockProduct($date, ['is_seasonal' => true]);
        $profile = RestaurantProfile::factory()->verified()->create();

        $html = $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->getContent();

        // The grade registry carries the real stock cap (50), not 0/null.
        $this->assertStringContainsString('\u0022cap\u0022:50', $html);
    }

    public function test_seasonal_out_of_stock_product_lives_in_the_requests_area(): void
    {
        $this->app->setLocale('de');
        $product = $this->seasonalOnRequestProduct();
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        // With no stocked day at all the shop shows its empty state and points
        // to the Anfragen area instead of listing anything.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertDontSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('data-testid="restaurant-no-stock-days"', false)
            ->assertSee(route('app.restaurant.requests.index'), false);

        // But requestable in the Anfragen area.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.requests.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('data-testid="requests-season-list"', false)
            ->assertSee(__('restaurant.request_submit'));
    }
}
