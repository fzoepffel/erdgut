<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopStockAudience;
use App\Mail\RestaurantOrderConfirmedMail;
use App\Mail\RestaurantOrderDeclinedMail;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminRestaurantOrderTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::tomorrow();
        Producer::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $this->product->id, 'received_on' => $this->date->toDateString(),
            'quantity' => 50, 'freshness_window_days' => 3,
        ]);
    }

    private function submittedOrder(): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        return app(RestaurantOrderService::class)->submit($profile->user, [
            'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
    }

    private function remaining(): float
    {
        return app(InventoryService::class)->availableOnDate($this->product, $this->date, ShopStockAudience::BusinessTrade);
    }

    public function test_non_admin_cannot_access_queue(): void
    {
        $this->get(route('app.admin.restaurant-orders.index'))->assertRedirect(route('auth.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.restaurant-orders.index'))->assertForbidden();

        $restaurant = RestaurantProfile::factory()->verified()->create()->user;
        $this->actingAs($restaurant)
            ->get(route('app.admin.restaurant-orders.index'))->assertForbidden();
    }

    public function test_queue_lists_and_filters(): void
    {
        $order = $this->submittedOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('app.admin.restaurant-orders.index'))
            ->assertOk()->assertSee(route('app.admin.restaurant-orders.show', $order), false);

        // Filtering to a status with no rows shows the empty state.
        $this->actingAs($admin)->get(route('app.admin.restaurant-orders.index', ['status' => 'declined']))
            ->assertOk()->assertDontSee(route('app.admin.restaurant-orders.show', $order), false);
    }

    public function test_confirm_transitions_audits_and_mails(): void
    {
        Mail::fake();
        $order = $this->submittedOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.restaurant-orders.confirm', $order), ['note' => 'Liefern Mittwoch'])
            ->assertRedirect(route('app.admin.restaurant-orders.show', $order));

        $this->assertSame(RestaurantOrderStatus::Confirmed, $order->fresh()->status);
        // Confirm keeps the hold.
        $this->assertEqualsWithDelta(40.0, $this->remaining(), 0.01);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'restaurant_order',
            'entity_id' => $order->id,
            'action' => 'confirm',
        ]);
        Mail::assertSent(RestaurantOrderConfirmedMail::class, fn ($m) => $m->hasTo($order->user->email) && $m->note === 'Liefern Mittwoch');
    }

    public function test_decline_releases_reservation_and_mails(): void
    {
        Mail::fake();
        $order = $this->submittedOrder();
        $admin = User::factory()->admin()->create();

        $this->assertEqualsWithDelta(40.0, $this->remaining(), 0.01); // held while submitted

        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.decline', $order))->assertRedirect();

        $this->assertSame(RestaurantOrderStatus::Declined, $order->fresh()->status);
        // Declining releases the hold.
        $this->assertEqualsWithDelta(50.0, $this->remaining(), 0.01);
        Mail::assertSent(RestaurantOrderDeclinedMail::class, fn ($m) => $m->hasTo($order->user->email));
    }

    public function test_fulfil_only_from_confirmed_and_sends_no_mail(): void
    {
        Mail::fake();
        $order = $this->submittedOrder();
        $admin = User::factory()->admin()->create();

        // Cannot fulfil a still-submitted order.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.fulfil', $order))->assertRedirect();
        $this->assertSame(RestaurantOrderStatus::Submitted, $order->fresh()->status);

        app(RestaurantOrderService::class)->confirm($order);
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.fulfil', $order))->assertRedirect();
        $this->assertSame(RestaurantOrderStatus::Fulfilled, $order->fresh()->status);

        Mail::assertNotSent(RestaurantOrderConfirmedMail::class);
        Mail::assertNotSent(RestaurantOrderDeclinedMail::class);
    }

    public function test_confirm_is_noop_on_non_submitted_order(): void
    {
        Mail::fake();
        $order = $this->submittedOrder();
        app(RestaurantOrderService::class)->cancel($order); // Cancelled
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.confirm', $order))->assertRedirect();
        $this->assertSame(RestaurantOrderStatus::Cancelled, $order->fresh()->status);
        Mail::assertNothingSent();
    }
}
