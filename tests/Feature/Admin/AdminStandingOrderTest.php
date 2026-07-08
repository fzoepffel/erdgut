<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantStandingOrderStatus;
use App\Models\AuditEvent;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Admin Lieferpläne page: manage every standing order without owner gating. */
class AdminStandingOrderTest extends TestCase
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

    /** An ACTIVE standing order with materialized future weeks (weekly line qty 5). */
    private function activeStanding(): RestaurantStandingOrder
    {
        $date = Carbon::tomorrow();
        $p = $this->product();
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => $date->toDateString(), 'quantity' => 500, 'freshness_window_days' => 60]);
        $profile = RestaurantProfile::factory()->verified()->create();

        $this->actingAs($profile->user)->post(route('app.restaurant.orders.store'), [
            'items' => [['product_id' => $p->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly']],
            'expected_delivery_date' => $date->toDateString(),
            'delivery_street' => 'Marktstr. 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
        ])->assertRedirect();
        auth()->logout();

        return RestaurantStandingOrder::firstOrFail();
    }

    public function test_index_gated_admin_only_and_lists_plans(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $standing = $this->activeStanding();

            $this->get(route('app.admin.standing-orders.index'))->assertRedirect(route('auth.login'));
            $this->actingAs(User::factory()->create())
                ->get(route('app.admin.standing-orders.index'))->assertForbidden();

            $this->actingAs(User::factory()->admin()->create())
                ->get(route('app.admin.standing-orders.index'))
                ->assertOk()
                ->assertSee('data-testid="admin-standing-'.$standing->id.'"', false)
                ->assertSee($standing->user->restaurantProfile->business_name);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_update_items_propagates_but_spares_adjusted_weeks(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // a Wednesday
        try {
            $standing = $this->activeStanding();
            $admin = User::factory()->admin()->create();

            $future = RestaurantOrder::where('source_standing_order_id', $standing->id)
                ->where('status', RestaurantOrderStatus::Confirmed->value)
                ->whereDate('expected_delivery_date', '>', Carbon::tomorrow())
                ->orderBy('expected_delivery_date')
                ->get();
            $this->assertGreaterThanOrEqual(2, $future->count());
            $adjustedWeek = $future[0];
            $adjustedWeek->forceFill(['adjusted_at' => now()])->save();

            $item = $standing->items()->firstOrFail();
            $this->actingAs($admin)
                ->post(route('app.admin.standing-orders.items', $standing), [
                    'lines' => [['id' => $item->id, 'quantity' => 9]],
                ])->assertRedirect();

            $rebuilt = RestaurantOrder::where('source_standing_order_id', $standing->id)
                ->where('status', RestaurantOrderStatus::Confirmed->value)
                ->whereDate('expected_delivery_date', '>', Carbon::tomorrow())
                ->where('id', '!=', $adjustedWeek->id)
                ->get();
            $this->assertGreaterThanOrEqual(1, $rebuilt->count());
            foreach ($rebuilt as $week) {
                $this->assertEqualsWithDelta(9.0, (float) $week->items()->firstOrFail()->quantity, 0.001);
            }
            $this->assertEqualsWithDelta(5.0, (float) $adjustedWeek->refresh()->items()->firstOrFail()->quantity, 0.001);

            $event = AuditEvent::query()
                ->where('entity_type', 'restaurant_standing_order')
                ->where('entity_id', $standing->id)
                ->where('action', 'admin_update_items')
                ->first();
            $this->assertNotNull($event);
            $this->assertSame('admin', $event->metadata['channel']);

            // Removing the last line is rejected by the service.
            $this->actingAs($admin)
                ->post(route('app.admin.standing-orders.items', $standing), [
                    'lines' => [['id' => $item->id, 'remove' => 1]],
                ])->assertSessionHasErrors('lines');
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_set_end_trims_weeks_beyond_the_new_end(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $standing = $this->activeStanding();
            $admin = User::factory()->admin()->create();
            $end = Carbon::tomorrow()->addWeeks(2);

            $this->actingAs($admin)
                ->post(route('app.admin.standing-orders.end', $standing), ['ends_on' => $end->toDateString()])
                ->assertRedirect();

            $this->assertSame($end->toDateString(), $standing->items()->firstOrFail()->refresh()->ends_on->toDateString());
            $remaining = RestaurantOrder::where('source_standing_order_id', $standing->id)
                ->where('status', RestaurantOrderStatus::Confirmed->value)
                ->get();
            $this->assertNotEmpty($remaining);
            foreach ($remaining as $week) {
                $this->assertTrue($week->expected_delivery_date->lte($end));
            }
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_pause_trims_future_weeks_and_resume_regenerates(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $standing = $this->activeStanding();
            $admin = User::factory()->admin()->create();
            $futureCount = fn (): int => RestaurantOrder::where('source_standing_order_id', $standing->id)
                ->whereDate('expected_delivery_date', '>', Carbon::tomorrow())
                ->count();
            $this->assertGreaterThan(0, $futureCount());

            $this->actingAs($admin)
                ->post(route('app.admin.standing-orders.pause', $standing))->assertRedirect();
            $this->assertSame(RestaurantStandingOrderStatus::Paused, $standing->refresh()->status);
            $this->assertSame(0, $futureCount());
            // The origin order survives.
            $this->assertNotNull(RestaurantOrder::where('source_standing_order_id', $standing->id)->first());

            $this->actingAs($admin)
                ->post(route('app.admin.standing-orders.resume', $standing))->assertRedirect();
            $this->assertSame(RestaurantStandingOrderStatus::Active, $standing->refresh()->status);
            $this->assertGreaterThan(0, $futureCount());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_cancel_sets_status_and_trims(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $standing = $this->activeStanding();

            $this->actingAs(User::factory()->admin()->create())
                ->delete(route('app.admin.standing-orders.destroy', $standing))->assertRedirect();

            $this->assertSame(RestaurantStandingOrderStatus::Cancelled, $standing->refresh()->status);
            $this->assertSame(0, RestaurantOrder::where('source_standing_order_id', $standing->id)
                ->whereDate('expected_delivery_date', '>', Carbon::tomorrow())
                ->count());
            $this->assertNotNull(AuditEvent::query()
                ->where('entity_type', 'restaurant_standing_order')
                ->where('action', 'admin_cancel')->first());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_activate_pending_standing_materializes_horizon(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            // A weekly SEASONAL line goes through the Anfragen area, leaving
            // the standing order Pending until someone confirms/activates.
            $p = $this->product(['is_seasonal' => true]);
            $profile = RestaurantProfile::factory()->verified()->create();
            app(RestaurantOrderService::class)->submitRequest($profile->user, [
                'items' => [['product_id' => $p->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly']],
                'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
                'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
            ]);
            $order = RestaurantOrder::latest('id')->firstOrFail();
            app(RestaurantStandingOrderService::class)->createFromOrder($order, [
                ['product_id' => $p->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly'],
            ]);
            $standing = RestaurantStandingOrder::firstOrFail();
            $this->assertTrue($standing->isPending());

            $admin = User::factory()->admin()->create();
            $this->actingAs($admin)
                ->post(route('app.admin.standing-orders.activate', $standing))->assertRedirect();

            $standing->refresh();
            $this->assertSame(RestaurantStandingOrderStatus::Active, $standing->status);
            $this->assertSame($admin->id, $standing->confirmed_by_user_id);
            $this->assertGreaterThan(0, RestaurantOrder::where('source_standing_order_id', $standing->id)
                ->where('id', '!=', $order->id)->count());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_order_detail_links_to_standing_order_management(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8));
        try {
            $standing = $this->activeStanding();
            $order = RestaurantOrder::where('source_standing_order_id', $standing->id)->firstOrFail();

            $this->actingAs(User::factory()->admin()->create())
                ->get(route('app.admin.restaurant-orders.show', $order))
                ->assertOk()
                ->assertSee('data-testid="admin-standing-manage-link"', false)
                ->assertSee('#standing-'.$standing->id);
        } finally {
            Carbon::setTestNow(null);
        }
    }
}
