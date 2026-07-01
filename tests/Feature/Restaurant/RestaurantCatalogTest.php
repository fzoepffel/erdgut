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
            ->assertSee(__('restaurant.in_stock')) // "Auf Lager"
            ->assertSee('9,35'); // 10.00 gross - 7% VAT, net (de number format)
    }

    public function test_date_query_switches_in_stock_set(): void
    {
        $this->app->setLocale('de');
        $stockDay = Carbon::tomorrow()->addDay();
        $product = $this->inStockProduct($stockDay); // freshness 3d from stockDay
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        // On the stock day the product is in stock.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog', ['date' => $stockDay->toDateString()]))
            ->assertOk()
            ->assertSee(__('restaurant.in_stock'));

        // A non-seasonal product with no stock on tomorrow is absent there.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog', ['date' => Carbon::tomorrow()->toDateString()]))
            ->assertOk()
            ->assertDontSee($product->name);
    }

    public function test_catalog_has_shop_style_chrome_tabs_nav_and_filters(): void
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
            ->assertSee('data-testid="restaurant-tabs"', false)
            ->assertSee('data-testid="restaurant-name-filter"', false)
            ->assertSee(__('restaurant.tab_in_stock'))
            ->assertSee(__('restaurant.tab_on_request'))
            ->assertSee(__('restaurant.tab_special'))
            // nav links to the two previously-buried destinations
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
            ->assertSee('overStock(', false)            // amount over-stock cue wired
            ->assertSee(__('restaurant.amount_on_request'))
            ->assertSee(__('restaurant.recurrence_open_ended'));
    }

    public function test_seasonal_out_of_stock_product_shows_on_request(): void
    {
        $this->app->setLocale('de');
        $product = $this->seasonalOnRequestProduct();
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee(__('restaurant.on_request')) // "auf Anfrage" badge
            // tagged into the season tab, not the in-stock tab
            ->assertSee('data-tab="season"', false);
    }
}
