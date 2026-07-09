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

    private function product(array $attrs = []): Product
    {
        Producer::factory()->create();

        return Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ], $attrs));
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
        // The shop sells only what's in stock — make sure both lines fit.
        if (! ProductStockInflow::where('product_id', $oneTime->id)->exists()) {
            ProductStockInflow::create(['product_id' => $oneTime->id, 'received_on' => $date->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
        }
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
        // The weekly line is in stock → the first order auto-confirms, which
        // activates the recurring delivery immediately (no admin step needed).
        $this->assertSame(RestaurantStandingOrderStatus::Active, $standing->status);
        $this->assertSame($date->dayOfWeek, $standing->delivery_weekday);
        $this->assertSame($standing->id, $order->source_standing_order_id);
        // Only the weekly line is in the standing order; recurrence starts next week.
        $this->assertSame(1, $standing->items()->count());
        $item = $standing->items()->firstOrFail();
        $this->assertSame($weekly->id, $item->product_id);
        $this->assertSame($date->copy()->addWeek()->toDateString(), $item->starts_on->toDateString());
    }

    public function test_confirming_a_recurring_request_activates_standing_order(): void
    {
        // A weekly SEASONAL line goes through the Anfragen area: the standing
        // order stays pending until the admin confirms the request.
        $date = Carbon::tomorrow();
        $p = $this->product(['is_seasonal' => true]);
        $user = $this->verifiedRestaurant();

        $this->actingAs($user)->post(route('app.restaurant.requests.store'), [
            'items' => [['product_id' => $p->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly']],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ])->assertRedirect();
        $order = RestaurantOrder::latest()->firstOrFail();
        $this->assertTrue($order->isRequest());
        $this->assertTrue(RestaurantStandingOrder::firstOrFail()->isPending());

        app(RestaurantOrderService::class)->confirm($order);

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

            // Activation (on confirm) already materialized the WHOLE horizon —
            // every upcoming week exists as a real, auto-confirmed order.
            $standingId = RestaurantStandingOrder::first()->id;
            $generated = RestaurantOrder::where('source_standing_order_id', $standingId)
                ->whereDate('original_delivery_date', $nextWeek->toDateString())->firstOrFail();
            $this->assertSame(RestaurantOrderStatus::Confirmed, $generated->status);
            $horizonCount = RestaurantOrder::where('source_standing_order_id', $standingId)
                ->where('id', '!=', $order->id)->count();
            $this->assertGreaterThanOrEqual(4, $horizonCount);
            // Reserved against the next week's stock.
            $this->assertEqualsWithDelta(45.0, app(InventoryService::class)->availableOnDate($p, $nextWeek, ShopStockAudience::BusinessTrade), 0.01);

            // The rolling sweep finds nothing new — idempotent.
            $svc = app(RestaurantStandingOrderService::class);
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

    public function test_owner_can_move_the_end_date(): void
    {
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 50, 'freshness_window_days' => 3]);
        $user = $this->verifiedRestaurant();
        $order = $this->submitRecurring($user, $p, $this->product(), $date);
        app(RestaurantOrderService::class)->confirm($order->refresh());
        $standing = RestaurantStandingOrder::firstOrFail();
        $newEnd = Carbon::today()->next($standing->delivery_weekday)->addWeeks(3);

        $this->actingAs($user)
            ->post(route('app.restaurant.standing-orders.end', $standing), ['ends_on' => $newEnd->toDateString()])
            ->assertRedirect();
        $this->assertSame($newEnd->toDateString(), $standing->items()->firstOrFail()->refresh()->ends_on->toDateString());

        // Past dates rejected; foreign owners blocked.
        $this->actingAs($user)
            ->post(route('app.restaurant.standing-orders.end', $standing), ['ends_on' => '2020-01-01'])
            ->assertSessionHasErrors('ends_on');
        $this->actingAs($this->verifiedRestaurant())
            ->post(route('app.restaurant.standing-orders.end', $standing), ['ends_on' => $newEnd->toDateString()])
            ->assertForbidden();
    }

    public function test_pause_trims_future_weeks_and_resume_regenerates(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 500, 'freshness_window_days' => 60]);
        $user = $this->verifiedRestaurant();
        $order = $this->submitRecurring($user, $p, $this->product(), $date);
        $standing = RestaurantStandingOrder::firstOrFail();
        app(RestaurantStandingOrderService::class)->activate($standing, null);
        $svc = app(RestaurantStandingOrderService::class);

        $future = fn () => RestaurantOrder::where('source_standing_order_id', $standing->id)
            ->where('id', '!=', $order->id)
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->count();
        $this->assertGreaterThanOrEqual(4, $future());

        // Pause cancels every future materialized week — the origin order survives.
        $svc->pause($standing->refresh());
        $this->assertSame(0, $future());
        $this->assertSame(RestaurantOrderStatus::Confirmed, $order->refresh()->status);

        // Resume regenerates the horizon.
        $svc->resume($standing->refresh());
        $this->assertGreaterThanOrEqual(4, $future());
        Carbon::setTestNow(null);
    }

    public function test_end_change_trims_weeks_beyond_the_new_end(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 500, 'freshness_window_days' => 60]);
        $user = $this->verifiedRestaurant();
        $order = $this->submitRecurring($user, $p, $this->product(), $date);
        $standing = RestaurantStandingOrder::firstOrFail();
        app(RestaurantStandingOrderService::class)->activate($standing, null);

        $newEnd = $date->copy()->addWeeks(2); // keep only the first two recurrence weeks
        $this->actingAs($user)
            ->post(route('app.restaurant.standing-orders.end', $standing), ['ends_on' => $newEnd->toDateString()])
            ->assertRedirect();

        $remaining = RestaurantOrder::where('source_standing_order_id', $standing->id)
            ->where('id', '!=', $order->id)
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->pluck('expected_delivery_date');
        $this->assertNotEmpty($remaining);
        foreach ($remaining as $d) {
            $this->assertLessThanOrEqual($newEnd->toDateString(), Carbon::parse($d)->toDateString());
        }
        Carbon::setTestNow(null);
    }

    public function test_orders_list_collapses_future_weeks_to_a_summary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 500, 'freshness_window_days' => 60]);
        $user = $this->verifiedRestaurant();
        $this->submitRecurring($user, $p, $this->product(), $date);
        $standing = RestaurantStandingOrder::firstOrFail();

        $futureCount = RestaurantOrder::where('source_standing_order_id', $standing->id)
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->count();
        $this->assertGreaterThanOrEqual(5, $futureCount);

        // The list shows the summary line + only the NEXT week individually.
        $html = $this->actingAs($user)
            ->get(route('app.restaurant.orders.index'))
            ->assertOk()
            ->assertSee('data-testid="recurring-summary-'.$standing->id.'"', false)
            ->getContent();
        $rows = substr_count($html, __('restaurant.order_ref', ['id' => '']));
        // Exactly ONE individual order row (origin = next occurrence).
        $this->assertSame(1, preg_match_all('/Bestellung #\d+/', $html));
        Carbon::setTestNow(null);
    }

    public function test_template_edit_propagates_but_spares_adjusted_and_closed_weeks(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 500, 'freshness_window_days' => 60]);
        $user = $this->verifiedRestaurant();
        $order = $this->submitRecurring($user, $p, $this->product(), $date);
        $standing = RestaurantStandingOrder::firstOrFail();
        app(RestaurantStandingOrderService::class)->activate($standing, null);

        $future = RestaurantOrder::where('source_standing_order_id', $standing->id)
            ->where('id', '!=', $order->id)
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->orderBy('expected_delivery_date')
            ->get();
        $this->assertGreaterThanOrEqual(3, $future->count());

        // Hand-adjust week 2; close ordering for week 3.
        $adjustedWeek = $future[1];
        $adjustedWeek->forceFill(['adjusted_at' => now()])->save();
        $closedWeek = $future[2];
        ShopClosedDay::create(['closed_on' => $closedWeek->expected_delivery_date->toDateString(), 'closes_at' => null]);

        // Template: weekly item 5 → 9.
        $item = $standing->items()->firstOrFail();
        $this->actingAs($user)
            ->post(route('app.restaurant.standing-orders.items', $standing), [
                'lines' => [['id' => $item->id, 'quantity' => 9]],
            ])
            ->assertRedirect();

        // Rebuilt weeks carry 9; the adjusted + closed weeks keep 5.
        $rebuilt = RestaurantOrder::where('source_standing_order_id', $standing->id)
            ->where('id', '!=', $order->id)
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->whereNotIn('id', [$adjustedWeek->id, $closedWeek->id])
            ->get();
        $this->assertGreaterThanOrEqual(1, $rebuilt->count());
        foreach ($rebuilt as $week) {
            $this->assertEqualsWithDelta(9.0, (float) $week->items()->firstOrFail()->quantity, 0.001, 'week '.$week->expected_delivery_date->toDateString());
        }
        $this->assertEqualsWithDelta(5.0, (float) $adjustedWeek->refresh()->items()->firstOrFail()->quantity, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $closedWeek->refresh()->items()->firstOrFail()->quantity, 0.001);

        // Last-item removal rejected; foreign owner blocked.
        $this->actingAs($user)
            ->post(route('app.restaurant.standing-orders.items', $standing), [
                'lines' => [['id' => $item->id, 'remove' => 1]],
            ])->assertSessionHasErrors('lines');
        $this->actingAs($this->verifiedRestaurant())
            ->post(route('app.restaurant.standing-orders.items', $standing), [
                'lines' => [['id' => $item->id, 'quantity' => 2]],
            ])->assertForbidden();
        Carbon::setTestNow(null);
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
