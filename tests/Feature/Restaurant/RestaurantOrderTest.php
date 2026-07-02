<?php

namespace Tests\Feature\Restaurant;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderItemTrack;
use App\Enums\RestaurantOrderLineStatus;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopStockAudience;
use App\Mail\RestaurantOrderAdminNotification;
use App\Mail\RestaurantOrderSubmittedMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RestaurantOrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs = []): Product
    {
        Producer::factory()->create();

        return Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'vat_rate_percent' => 7,
            'is_seasonal' => false,
            'is_available' => true,
            'is_published' => true,
        ], $attrs));
    }

    private function stock(Product $product, Carbon $date, float $qty = 50): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $date->toDateString(),
            'quantity' => $qty,
            'freshness_window_days' => 3,
        ]);
    }

    private function verifiedRestaurant(): User
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();

        return $profile->user;
    }

    // --- Guard matrix ---------------------------------------------------

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('app.restaurant.orders.index'))->assertRedirect(route('auth.login'));
    }

    public function test_customer_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.restaurant.orders.index'))->assertForbidden();
    }

    public function test_pending_restaurant_redirected_to_pending(): void
    {
        $profile = RestaurantProfile::factory()->create();
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.orders.index'))->assertRedirect(route('app.restaurant.pending'));
    }

    // --- Submit ---------------------------------------------------------

    public function test_submit_creates_order_reserves_in_stock_and_mails(): void
    {
        Mail::fake();
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 50);
        User::factory()->admin()->create();
        $user = $this->verifiedRestaurant();

        $response = $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 12]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'Marktstr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
        ]);

        $order = RestaurantOrder::firstOrFail();
        $response->assertRedirect(route('app.restaurant.orders.show', $order));

        $this->assertSame($user->id, $order->user_id);
        // All lines in stock → the order is a firm, auto-confirmed "Bestellung".
        $this->assertSame(RestaurantOrderStatus::Confirmed, $order->status);
        $item = $order->items()->firstOrFail();
        $this->assertSame(RestaurantOrderItemTrack::Reserved, $item->track);
        $this->assertSame(RestaurantOrderLineStatus::Confirmed, $item->line_status);
        // 12 × €10 gross = €120; net = 120 − 120*7/107 = 112.15
        $this->assertEqualsWithDelta(112.15, (float) $order->items_total_net_eur, 0.01);

        // Reservation reduces availability.
        $this->assertEqualsWithDelta(38.0, app(InventoryService::class)->availableOnDate($product, $date, ShopStockAudience::BusinessTrade), 0.01);

        Mail::assertSent(RestaurantOrderSubmittedMail::class, fn ($m) => $m->hasTo($user->email));
        Mail::assertSent(RestaurantOrderAdminNotification::class);
    }

    public function test_out_of_stock_seasonal_line_becomes_a_request(): void
    {
        $date = Carbon::tomorrow();
        // Seasonal (in window), no stock → on request.
        $product = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 1, 'season_start_day' => 1,
            'season_end_month' => 12, 'season_end_day' => 31,
        ]);
        $user = $this->verifiedRestaurant();

        app(RestaurantOrderService::class)->submit($user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 5]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $this->assertSame(RestaurantOrderItemTrack::Requested, RestaurantOrder::firstOrFail()->items()->firstOrFail()->track);
        // A requested line reserves nothing.
        $this->assertEqualsWithDelta(0.0, app(InventoryService::class)->availableOnDate($product, $date, ShopStockAudience::BusinessTrade), 0.01);
    }

    public function test_business_price_rule_is_snapshotted(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product(['unit_price' => 10.00]);
        $this->stock($product, $date, 50);
        ProductPriceRule::create([
            'product_id' => $product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 8.00, 'priority' => 0, 'is_active' => true,
        ]);
        $user = $this->verifiedRestaurant();

        $order = app(RestaurantOrderService::class)->submit($user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $item = $order->items()->firstOrFail();
        $this->assertEqualsWithDelta(8.00, (float) $item->unit_price_snapshot_eur, 0.001);
        $this->assertEqualsWithDelta(16.00, (float) $item->line_total_eur, 0.01);
    }

    public function test_submit_rejects_closed_date(): void
    {
        $product = $this->product();
        $user = $this->verifiedRestaurant();

        $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
            'expected_delivery_date' => Carbon::today()->toDateString(), // today is never open
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ])->assertSessionHasErrors('expected_delivery_date');

        $this->assertSame(0, RestaurantOrder::count());
    }

    // --- My requests / cancel -------------------------------------------

    public function test_owner_sees_their_request_and_can_cancel(): void
    {
        $this->app->setLocale('de');
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 50);
        $user = $this->verifiedRestaurant();
        $order = app(RestaurantOrderService::class)->submit($user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $this->actingAs($user)->get(route('app.restaurant.orders.index'))->assertOk()
            ->assertSee(route('app.restaurant.orders.show', $order), false);
        $this->actingAs($user)->get(route('app.restaurant.orders.show', $order))->assertOk()->assertSee($product->name);

        $this->assertEqualsWithDelta(40.0, app(InventoryService::class)->availableOnDate($product, $date, ShopStockAudience::BusinessTrade), 0.01);

        $this->actingAs($user)->post(route('app.restaurant.orders.cancel', $order))->assertRedirect();
        $this->assertSame(RestaurantOrderStatus::Cancelled, $order->fresh()->status);
        // Cancel releases the reservation.
        $this->assertEqualsWithDelta(50.0, app(InventoryService::class)->availableOnDate($product, $date, ShopStockAudience::BusinessTrade), 0.01);
    }

    public function test_reorder_creates_a_new_order_for_the_next_open_day(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 50);
        $user = $this->verifiedRestaurant();
        $original = app(RestaurantOrderService::class)->submit($user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'Marktstr. 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
        ]);

        $this->actingAs($user)
            ->post(route('app.restaurant.orders.reorder', $original))
            ->assertRedirect();

        $this->assertSame(2, RestaurantOrder::count());
        $new = RestaurantOrder::latest('id')->firstOrFail();
        $this->assertNotSame($original->id, $new->id);
        $item = $new->items()->firstOrFail();
        $this->assertSame($product->id, $item->product_id);
        $this->assertEqualsWithDelta(10.0, (float) $item->quantity, 0.001);
        // Address copied; recurrence never carries over.
        $this->assertSame('Marktstr. 1', $new->delivery_street);
        $this->assertSame('one_time', $item->recurrence);

        // Owner-gated.
        $other = $this->verifiedRestaurant();
        $this->actingAs($other)->post(route('app.restaurant.orders.reorder', $original))->assertForbidden();
    }

    public function test_non_owner_cannot_view_or_cancel(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 50);
        $owner = $this->verifiedRestaurant();
        $order = app(RestaurantOrderService::class)->submit($owner, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $other = $this->verifiedRestaurant();
        $this->actingAs($other)->get(route('app.restaurant.orders.show', $order))->assertForbidden();
        $this->actingAs($other)->post(route('app.restaurant.orders.cancel', $order))->assertForbidden();
        // In-stock order auto-confirms; the non-owner's blocked cancel left it untouched.
        $this->assertSame(RestaurantOrderStatus::Confirmed, $order->fresh()->status);
    }
}
