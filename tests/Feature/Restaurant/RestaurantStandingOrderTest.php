<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantStandingOrderStatus;
use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\ShopClosedDay;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RestaurantStandingOrderTest extends TestCase
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

    /** Submit a first order with a weekly + a one-time line via HTTP. */
    private function submitRecurring(User $user, Product $weekly, Product $oneTime, Carbon $date): RestaurantOrder
    {
        $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
            'items' => [
                ['product_id' => $weekly->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly'],
                ['product_id' => $oneTime->id, 'variant_id' => null, 'quantity' => 2, 'recurrence' => 'one_time'],
            ],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'Marktstr. 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
        ])->assertRedirect();

        return RestaurantOrder::latest()->firstOrFail();
    }

    public function test_weekly_line_creates_pending_standing_order_excluding_one_time(): void
    {
        $date = Carbon::tomorrow();
        $weekly = $this->product();
        $oneTime = $this->product();
        ProductStockInflow::create(['product_id' => $weekly->id, 'received_on' => $date->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
        ProductStockInflow::create(['product_id' => $oneTime->id, 'received_on' => $date->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
        $user = $this->verifiedRestaurant();

        $order = $this->submitRecurring($user, $weekly, $oneTime, $date);

        $standing = RestaurantStandingOrder::firstOrFail();
        $this->assertSame(RestaurantStandingOrderStatus::Pending, $standing->status);
        $this->assertSame($date->dayOfWeek, $standing->delivery_weekday);
        $this->assertSame($standing->id, $order->source_standing_order_id);
        // Only the weekly line is in the standing order; recurrence starts next week.
        $this->assertSame(1, $standing->items()->count());
        $item = $standing->items()->firstOrFail();
        $this->assertSame($weekly->id, $item->product_id);
        $this->assertSame($date->copy()->addWeek()->toDateString(), $item->starts_on->toDateString());
    }

    public function test_confirming_first_order_activates_standing_order(): void
    {
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
        $user = $this->verifiedRestaurant();
        $order = $this->submitRecurring($user, $p, $this->product(), $date);

        $this->assertTrue(RestaurantStandingOrder::firstOrFail()->isPending());

        app(RestaurantOrderService::class)->confirm($order->refresh());

        $this->assertSame(RestaurantStandingOrderStatus::Active, RestaurantStandingOrder::firstOrFail()->status);
    }

    public function test_materialize_generates_auto_confirmed_order_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        try {
            $p = $this->product();
            // Stock the recurring delivery day (one week out).
            $deliveryDay = Carbon::now()->copy()->addWeek()->next(Carbon::now()->dayOfWeek);
            // Simpler: standing order weekday = today's weekday; next occurrence is +7 days.
            $firstDate = Carbon::now()->copy()->addDay(); // tomorrow = first manual delivery
            $nextWeek = $firstDate->copy()->addWeek();
            ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $firstDate->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
            ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $nextWeek->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);

            $user = $this->verifiedRestaurant();
            $order = $this->submitRecurring($user, $p, $this->product(), $firstDate);
            app(RestaurantOrderService::class)->confirm($order->refresh());

            $svc = app(RestaurantStandingOrderService::class);
            $written = $svc->materializeDue(Carbon::now());
            $this->assertSame(1, $written);

            $generated = RestaurantOrder::where('source_standing_order_id', RestaurantStandingOrder::first()->id)
                ->whereDate('original_delivery_date', $nextWeek->toDateString())->firstOrFail();
            $this->assertSame(RestaurantOrderStatus::Confirmed, $generated->status);
            // Reserved against the next week's stock.
            $this->assertEqualsWithDelta(45.0, app(InventoryService::class)->availableOnDate($p, $nextWeek, ShopStockAudience::BusinessTrade), 0.01);

            // Idempotent.
            $this->assertSame(0, $svc->materializeDue(Carbon::now()));
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_materialize_skips_closed_dates_and_inactive_standing_orders(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $p = $this->product();
            $firstDate = Carbon::now()->copy()->addDay();
            $nextWeek = $firstDate->copy()->addWeek();
            ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $firstDate->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);

            $user = $this->verifiedRestaurant();
            $order = $this->submitRecurring($user, $p, $this->product(), $firstDate);
            app(RestaurantOrderService::class)->confirm($order->refresh());

            // Close the next delivery day → skipped.
            ShopClosedDay::create(['closed_on' => $nextWeek->toDateString(), 'closes_at' => null]);
            $this->assertSame(0, app(RestaurantStandingOrderService::class)->materializeDue(Carbon::now()));

            // Paused standing order generates nothing even when open.
            ShopClosedDay::query()->delete();
            RestaurantStandingOrder::query()->update(['status' => RestaurantStandingOrderStatus::Paused->value]);
            ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $nextWeek->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
            $this->assertSame(0, app(RestaurantStandingOrderService::class)->materializeDue(Carbon::now()));
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_line_past_its_end_date_is_dropped(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $p = $this->product();
            $firstDate = Carbon::now()->copy()->addDay();
            $nextWeek = $firstDate->copy()->addWeek();
            ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $firstDate->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);

            $user = $this->verifiedRestaurant();
            // Weekly "until" = the first date itself → no future weeks active.
            $this->actingAs($user)->post(route('app.restaurant.orders.store'), [
                'items' => [['product_id' => $p->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly', 'until' => $firstDate->toDateString()]],
                'expected_delivery_date' => $firstDate->toDateString(),
                'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
            ])->assertRedirect();
            app(RestaurantOrderService::class)->confirm(RestaurantOrder::latest()->firstOrFail());

            // The line's ends_on (firstDate) is before nextWeek → no order generated.
            $this->assertSame(0, app(RestaurantStandingOrderService::class)->materializeDue(Carbon::now()));
            $this->assertSame(0, RestaurantOrder::whereNotNull('source_standing_order_id')->whereDate('original_delivery_date', $nextWeek->toDateString())->count());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_pause_resume_delete_owner_gated(): void
    {
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
        $user = $this->verifiedRestaurant();
        $order = $this->submitRecurring($user, $p, $this->product(), $date);
        app(RestaurantOrderService::class)->confirm($order->refresh());
        $standing = RestaurantStandingOrder::firstOrFail();

        // Non-owner blocked.
        $other = $this->verifiedRestaurant();
        $this->actingAs($other)->post(route('app.restaurant.standing-orders.pause', $standing))->assertForbidden();

        $this->actingAs($user)->post(route('app.restaurant.standing-orders.pause', $standing))->assertRedirect();
        $this->assertSame(RestaurantStandingOrderStatus::Paused, $standing->refresh()->status);
        $this->actingAs($user)->post(route('app.restaurant.standing-orders.resume', $standing))->assertRedirect();
        $this->assertSame(RestaurantStandingOrderStatus::Active, $standing->refresh()->status);
        $this->actingAs($user)->delete(route('app.restaurant.standing-orders.destroy', $standing))->assertRedirect();
        $this->assertSame(RestaurantStandingOrderStatus::Cancelled, $standing->refresh()->status);

        // Index renders for the owner.
        $this->actingAs($user)->get(route('app.restaurant.standing-orders.index'))->assertOk();
    }
}
