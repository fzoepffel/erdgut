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
use App\Models\ShopClosedDay;
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

        Mail::assertQueued(RestaurantOrderSubmittedMail::class, fn ($m) => $m->hasTo($user->email));
        Mail::assertQueued(RestaurantOrderAdminNotification::class);
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

        app(RestaurantOrderService::class)->submitRequest($user, [
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

        $order = app(RestaurantOrderService::class)->submitOrder($user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $item = $order->items()->firstOrFail();
        // 8,00 € NET business rule → 8,56 € gross snapshot; the invoice
        // derives the 8,00 net back out of it.
        $this->assertEqualsWithDelta(8.56, (float) $item->unit_price_snapshot_eur, 0.001);
        $this->assertEqualsWithDelta(17.12, (float) $item->line_total_eur, 0.01);
        $this->assertEqualsWithDelta(16.00, $item->lineNetEur(), 0.01);
    }

    public function test_restaurant_order_price_ignores_consumer_volume_tiers(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 500);
        // Consumer tier: 3+ pieces → 1,00 € (would massively undercut B2B).
        ProductPriceRule::factory()->create(['product_id' => $product->id, 'min_quantity' => 3, 'unit_price' => 1.0]);
        $profile = RestaurantProfile::factory()->verified()->create();

        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 50]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        // Snapshot must be the product base/business price (10,00), not the tier.
        $item = $order->items()->firstOrFail();
        $this->assertEqualsWithDelta(10.0, (float) $item->unit_price_snapshot_eur, 0.001);
    }

    public function test_shop_rejects_over_stock_line_with_422(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 10);
        $user = $this->verifiedRestaurant();

        $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 50]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ])->assertSessionHasErrors('items');

        // Never silently converted into a request.
        $this->assertSame(0, RestaurantOrder::count());
    }

    public function test_reorder_hands_lines_into_the_shop_basket(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 50);
        $profile = RestaurantProfile::factory()->verified()->create();
        $original = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 40]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        // The button redirects into the shop with the prefill param; NO new
        // order is created (Arthur misclicked and got a duplicate before).
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.reorder', $original))
            ->assertRedirect(route('app.restaurant.catalog', ['reorder' => $original->id]));
        $this->assertSame(1, RestaurantOrder::count());

        // The catalog carries the prefill payload for the basket.
        $html = $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog', ['reorder' => $original->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('reorderPrefill', $html);
        $this->assertStringContainsString('\u0022orderId\u0022:'.$original->id, $html);

        // Foreign owners cannot trigger the handoff; foreign prefill is ignored.
        $other = RestaurantProfile::factory()->verified()->create();
        $this->actingAs($other->user)
            ->post(route('app.restaurant.orders.reorder', $original))
            ->assertForbidden();
        $otherHtml = $this->actingAs($other->user)
            ->get(route('app.restaurant.catalog', ['reorder' => $original->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('reorderPrefill: null', $otherHtml);
    }

    public function test_reorder_banner_names_missing_lines_and_links_the_day_that_covers_all(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        try {
            $laterDay = Carbon::tomorrow()->addWeek();
            $everyDay = $this->product();
            $lateOnly = $this->product();
            $this->stock($everyDay, Carbon::tomorrow(), 50);
            $this->stock($everyDay, $laterDay, 50);
            $this->stock($lateOnly, $laterDay, 50);

            $profile = RestaurantProfile::factory()->verified()->create();
            $original = app(RestaurantOrderService::class)->submitOrder($profile->user, [
                'items' => [
                    ['product_id' => $everyDay->id, 'variant_id' => null, 'quantity' => 10],
                    ['product_id' => $lateOnly->id, 'variant_id' => null, 'quantity' => 10],
                ],
                'expected_delivery_date' => $laterDay->toDateString(),
                'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
            ]);

            // Reorder lands on the FIRST stocked day (tomorrow), where the
            // late-only product is missing: it is named, and the banner links
            // the day that covers everything.
            $html = $this->actingAs($profile->user)
                ->get(route('app.restaurant.catalog', ['reorder' => $original->id]))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString('\u0022missingNames\u0022', $html);
            $this->assertStringContainsString($lateOnly->name, $html);
            $this->assertStringContainsString('\u0022iso\u0022:\u0022'.$laterDay->toDateString().'\u0022', $html);
            $this->assertStringContainsString('\u0022all\u0022:true', $html);

            // Following the link keeps the prefill on the better day; there
            // everything is available, so no better date is suggested.
            $laterHtml = $this->actingAs($profile->user)
                ->get(route('app.restaurant.catalog', ['reorder' => $original->id, 'date' => $laterDay->toDateString()]))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString('\u0022missingNames\u0022:[]', $laterHtml);
            $this->assertStringContainsString('\u0022betterDate\u0022:null', $laterHtml);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_reorder_banner_suggests_the_day_covering_most_lines_when_none_covers_all(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        try {
            $laterDay = Carbon::tomorrow()->addWeek();
            $everyDay = $this->product();
            $lateOnly = $this->product();
            $goneForGood = $this->product();
            $this->stock($everyDay, Carbon::tomorrow(), 50);
            $this->stock($everyDay, $laterDay, 50);
            $this->stock($lateOnly, $laterDay, 50);
            // Exactly the ordered quantity: the original order consumes it,
            // so this line is never fully available again.
            $this->stock($goneForGood, $laterDay, 10);

            $profile = RestaurantProfile::factory()->verified()->create();
            $original = app(RestaurantOrderService::class)->submitOrder($profile->user, [
                'items' => [
                    ['product_id' => $everyDay->id, 'variant_id' => null, 'quantity' => 10],
                    ['product_id' => $lateOnly->id, 'variant_id' => null, 'quantity' => 10],
                    ['product_id' => $goneForGood->id, 'variant_id' => null, 'quantity' => 10],
                ],
                'expected_delivery_date' => $laterDay->toDateString(),
                'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
            ]);

            // Tomorrow covers 1 of 3 lines; the later day covers 2 of 3 and no
            // day covers everything, so the partial suggestion appears.
            $html = $this->actingAs($profile->user)
                ->get(route('app.restaurant.catalog', ['reorder' => $original->id]))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString($lateOnly->name, $html);
            $this->assertStringContainsString($goneForGood->name, $html);
            $this->assertStringContainsString('\u0022iso\u0022:\u0022'.$laterDay->toDateString().'\u0022', $html);
            $this->assertStringContainsString('\u0022all\u0022:false', $html);
            $this->assertStringContainsString('\u0022available\u0022:2', $html);
            $this->assertStringContainsString('\u0022total\u0022:3', $html);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_non_owner_cannot_view_or_cancel(): void
    {
        $date = Carbon::tomorrow();
        $product = $this->product();
        $this->stock($product, $date, 50);
        $owner = $this->verifiedRestaurant();
        $order = app(RestaurantOrderService::class)->submitOrder($owner, [
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

    public function test_owner_can_adjust_a_confirmed_delivery(): void
    {
        Mail::fake();
        User::factory()->admin()->create(); // notification recipient
        $date = Carbon::tomorrow()->addDay();
        $a = $this->product(['unit_price' => 2.0]);
        $b = $this->product(['unit_price' => 5.0]);
        $this->stock($a, $date, 50);
        $this->stock($b, $date, 50);
        $profile = RestaurantProfile::factory()->verified()->create();
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [
                ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 10],
                ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 4],
            ],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        [$lineA, $lineB] = $order->items()->orderBy('id')->get();

        // Reduce A to 8, remove B entirely.
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.update-lines', $order), [
                'lines' => [
                    ['id' => $lineA->id, 'quantity' => 8],
                    ['id' => $lineB->id, 'remove' => 1],
                ],
            ])
            ->assertRedirect(route('app.restaurant.orders.show', $order));

        $order->refresh();
        $this->assertSame(1, $order->items()->count());
        $this->assertEqualsWithDelta(8.0, (float) $order->items()->first()->quantity, 0.001);
        $this->assertNotNull($order->adjusted_at);
        // Totals rerolled: 8 × 2,00 € gross = 16,00 €.
        $this->assertEqualsWithDelta(16.0, (float) $order->items_total_gross_eur, 0.01);
        // Admin got the heads-up (packing changes).
        Mail::assertQueued(RestaurantOrderAdminNotification::class, fn ($m) => $m->adjusted === true);
        // Freed stock is available again: 50 - 8 = 42.
        $this->assertEqualsWithDelta(42.0, app(InventoryService::class)->availableOnDate($a, $date, ShopStockAudience::BusinessTrade), 0.01);
    }

    public function test_owner_adjust_guards_stock_last_line_and_ownership(): void
    {
        $date = Carbon::tomorrow()->addDay();
        $a = $this->product();
        $this->stock($a, $date, 20);
        $profile = RestaurantProfile::factory()->verified()->create();
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $a->id, 'variant_id' => null, 'quantity' => 15]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        $line = $order->items()->firstOrFail();

        // Growing works up to remaining + own (5 + 15 = 20)…
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.update-lines', $order), [
                'lines' => [['id' => $line->id, 'quantity' => 20]],
            ])->assertSessionHasNoErrors();
        // …but not beyond.
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.update-lines', $order), [
                'lines' => [['id' => $line->id, 'quantity' => 21]],
            ])->assertSessionHasErrors('lines');

        // The last line cannot be removed (skip the delivery instead).
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.update-lines', $order), [
                'lines' => [['id' => $line->id, 'remove' => 1]],
            ])->assertSessionHasErrors('lines');

        // Foreign owner blocked.
        $other = RestaurantProfile::factory()->verified()->create();
        $this->actingAs($other->user)
            ->post(route('app.restaurant.orders.update-lines', $order), [
                'lines' => [['id' => $line->id, 'quantity' => 5]],
            ])->assertForbidden();
    }

    public function test_owner_changes_lock_when_the_shop_closes_for_the_day(): void
    {
        $date = Carbon::tomorrow()->addDay();
        $a = $this->product();
        $this->stock($a, $date, 20);
        $profile = RestaurantProfile::factory()->verified()->create();
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $a->id, 'variant_id' => null, 'quantity' => 5]],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        // Close ordering for that delivery day (manual closure, immediate).
        ShopClosedDay::create(['closed_on' => $date->toDateString(), 'closes_at' => null]);

        $line = $order->items()->firstOrFail();
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.update-lines', $order), [
                'lines' => [['id' => $line->id, 'quantity' => 4]],
            ])->assertSessionHasErrors('lines');

        // Cancel is locked too (flash, no state change).
        $this->actingAs($profile->user)
            ->post(route('app.restaurant.orders.cancel', $order))
            ->assertRedirect(route('app.restaurant.orders.show', $order));
        $this->assertSame('confirmed', $order->refresh()->status->value);

        // The detail page shows the locked note instead of the buttons.
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.orders.show', $order))
            ->assertOk()
            ->assertSee('data-testid="restaurant-order-locked-note"', false)
            ->assertDontSee('data-testid="restaurant-order-adjust-toggle"', false);
    }
}
