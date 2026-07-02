<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Enums\RestaurantStandingOrderStatus;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\RestaurantStandingOrderItem;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The restaurant dashboard: next delivery day, stats, upcoming/recent orders,
 * standing orders, invoices, reorder buttons, and the opportunity tiles.
 */
class RestaurantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function inStockProduct(Carbon $date): Product
    {
        Producer::factory()->create();
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id, 'received_on' => $date->toDateString(),
            'quantity' => 50, 'freshness_window_days' => 3,
        ]);

        return $product;
    }

    public function test_dashboard_shows_next_day_stats_orders_standing_and_tiles(): void
    {
        $this->app->setLocale('de');
        $date = Carbon::tomorrow();
        $product = $this->inStockProduct($date);
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        // One upcoming order.
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        // One active standing order.
        $standing = RestaurantStandingOrder::create([
            'user_id' => $profile->user->id, 'delivery_weekday' => $date->dayOfWeek,
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
            'status' => RestaurantStandingOrderStatus::Active, 'locale' => 'de',
        ]);
        RestaurantStandingOrderItem::create([
            'restaurant_standing_order_id' => $standing->id, 'product_id' => $product->id,
            'quantity' => 5, 'unit' => 'piece', 'starts_on' => $date->copy()->addWeek()->toDateString(),
        ]);

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="dashboard-next-day"', false)
            ->assertSee('data-testid="dashboard-order-cta"', false)
            ->assertSee(__('restaurant.dashboard_upcoming_title'))
            ->assertSee(__('restaurant.dashboard_recent_title'))
            ->assertSee(__('restaurant.opportunity_weekly_title'))
            ->assertSee(__('restaurant.opportunity_amounts_title'))
            ->assertSee(__('restaurant.opportunity_special_title'))
            ->assertSee(route('app.restaurant.orders.show', $order), false)
            ->assertSee(route('app.restaurant.orders.reorder', $order), false);
    }

    public function test_fresh_restaurant_dashboard_leads_with_opportunities(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        // A fresh account skips the zero stats + four empty cards; the
        // opportunity tiles carry the page instead.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.dashboard'))
            ->assertOk()
            ->assertDontSee(__('restaurant.dashboard_upcoming_empty'))
            ->assertDontSee(__('restaurant.dashboard_stat_upcoming'))
            ->assertSee(__('restaurant.opportunity_weekly_title'))
            ->assertSee(__('restaurant.opportunity_amounts_title'))
            ->assertSee(__('restaurant.opportunity_special_title'))
            ->assertSee('data-testid="dashboard-order-cta"', false);
    }
}
