<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderItemTrack;
use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RestaurantSpecialRequestTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        Producer::factory()->create();

        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ]);
    }

    private function verifiedRestaurant(): User
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        return $profile->user;
    }

    public function test_special_line_persists_as_unpriced_requested_custom_line(): void
    {
        $user = $this->verifiedRestaurant();

        $order = app(RestaurantOrderService::class)->submit($user, [
            'items' => [['name' => 'Wildkräuter', 'unit' => 'g', 'quantity' => 500]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $item = $order->items()->firstOrFail();
        $this->assertNull($item->product_id);
        $this->assertTrue($item->isCustom());
        $this->assertSame('Wildkräuter', $item->name_snapshot);
        $this->assertSame(RestaurantOrderItemTrack::Requested, $item->track);
        $this->assertEqualsWithDelta(0.0, (float) $item->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $order->items_total_net_eur, 0.001);
    }

    public function test_mixed_order_keeps_catalog_reservation_and_totals(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        ProductStockInflow::create([
            'product_id' => $product->id, 'received_on' => $date->toDateString(),
            'quantity' => 50, 'freshness_window_days' => 3,
        ]);
        $user = $this->verifiedRestaurant();

        $order = app(RestaurantOrderService::class)->submit($user, [
            'items' => [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 12],
                ['name' => 'Erbsentriebe', 'unit' => 'bunch', 'quantity' => 3],
            ],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $this->assertSame(2, $order->items()->count());
        // Catalog line reserved → availability dropped; the special line reserves nothing.
        $this->assertEqualsWithDelta(38.0, app(InventoryService::class)->availableOnDate($product, $date, ShopStockAudience::BusinessTrade), 0.01);
        // Totals count only the catalog line (12 × €10 gross → €112.15 net).
        $this->assertEqualsWithDelta(112.15, (float) $order->items_total_net_eur, 0.01);
    }

    public function test_custom_only_order_is_valid_via_http(): void
    {
        $user = $this->verifiedRestaurant();

        $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [['name' => 'Holunderblüten', 'unit' => 'bunch', 'quantity' => 5]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'Marktstr. 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
        ])->assertRedirect();

        $this->assertSame(1, RestaurantOrder::count());
    }

    public function test_validation_rejects_line_without_product_or_name(): void
    {
        $user = $this->verifiedRestaurant();

        $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [['quantity' => 2]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ])->assertSessionHasErrors('items.0.name');

        $this->assertSame(0, RestaurantOrder::count());
    }

    public function test_validation_requires_unit_for_special_line(): void
    {
        $user = $this->verifiedRestaurant();

        $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [['name' => 'Wildkräuter', 'quantity' => 2]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ])->assertSessionHasErrors('items.0.unit');
    }

    public function test_special_line_renders_with_price_on_request(): void
    {
        $this->app->setLocale('de');
        $user = $this->verifiedRestaurant();
        $order = app(RestaurantOrderService::class)->submit($user, [
            'items' => [['name' => 'Wildkräuter', 'unit' => 'g', 'quantity' => 500]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $this->actingAs($user)->get(route('app.restaurant.orders.show', $order))
            ->assertOk()
            ->assertSee('Wildkräuter')
            ->assertSee(__('restaurant.price_on_request'));
    }
}
