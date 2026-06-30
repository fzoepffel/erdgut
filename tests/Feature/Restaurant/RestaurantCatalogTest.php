<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantProfile;
use App\Models\User;
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
}
