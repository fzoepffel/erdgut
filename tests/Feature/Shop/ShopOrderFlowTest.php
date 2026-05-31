<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ShopOrderService;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * End-to-end coverage for the anonymous, multi-product general shop —
 * the admin-managed successor to the hard-coded spargel channel.
 *
 * Flow: storefront (GET /shop) → submit (POST /shop) → SumUp pay
 * (GET /shop/{order}/pay) → SumUp redirect (GET /shop/{order}/return).
 *
 * Asserts at each hop: only date-available products surface, the cart
 * persists with snapshot prices + correct totals, stock is re-checked
 * race-tight, guest checkout needs no auth, and SumUp verification drives
 * the right status transitions — all decoupled from User/Order/Subscription.
 */
class ShopOrderFlowTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    /** A weekday comfortably inside the 8-week availability horizon. */
    private const DELIVERY_DATE = '2026-06-05';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin "now" so DELIVERY_DATE is always a future, bookable date.
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));

        $producer = Producer::factory()->create();

        // Piece-unit product → canonical factor 1, so quantity × unit_price
        // is the line total with no per-unit conversion noise.
        $this->product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        // 10 pieces available on the delivery date (freshness=1 → that day only).
        ProductStockInflow::create([
            'product_id' => $this->product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 10,
            'freshness_window_days' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /** Minimal valid pickup payload for the chosen date. */
    private function pickupPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'quantities' => [$this->product->id => 3],
            'consent' => '1',
        ], $overrides);
    }

    public function test_storefront_renders_publicly_without_auth(): void
    {
        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertSee(__('shop.heading_main'))
            ->assertSee($this->product->name);
    }

    public function test_storefront_shows_only_products_available_on_selected_date(): void
    {
        // A second product with NO stock on the delivery date must be absent.
        $hidden = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'unit_price' => 2.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertDontSee($hidden->name);
    }

    public function test_guest_checkout_creates_order_with_items_and_correct_totals(): void
    {
        $response = $this->post(route('public.shop.store'), $this->pickupPayload());

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('Anna Mustermann', $order->name);
        $this->assertSame('pickup', $order->fulfillment_mode);
        $this->assertSame('karl_kunger', $order->pickup_location);
        $this->assertSame(ShopOrderStatus::PendingPayment, $order->status);
        $this->assertSame(self::DELIVERY_DATE, $order->expected_delivery_date->toDateString());

        // 3 × 4.00 = 12.00, pickup → no delivery fee.
        $this->assertSame('12.00', (string) $order->items_total_eur);
        $this->assertSame('0.00', (string) $order->delivery_fee_eur);
        $this->assertSame('12.00', (string) $order->total_amount_eur);

        // One cart line with snapshot price + unit.
        $this->assertSame(1, $order->items()->count());
        $item = $order->items()->first();
        $this->assertSame($this->product->id, $item->product_id);
        $this->assertSame('piece', $item->unit);
        $this->assertSame(4.0, (float) $item->unit_price_snapshot_eur);
        $this->assertSame('12.00', (string) $item->line_total_eur);

        $response->assertRedirect(route('public.shop.pay', ['shopOrder' => $order->id]));
    }

    public function test_delivery_order_adds_delivery_fee(): void
    {
        $this->post(route('public.shop.store'), $this->pickupPayload([
            'fulfillment_mode' => 'delivery',
            'pickup_location' => null,
            'phone' => '+49 30 12345678',
            'delivery_street' => 'Musterstraße 12',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
        ]))->assertRedirect();

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertSame('delivery', $order->fulfillment_mode);
        // 12.00 items + 2.90 delivery = 14.90.
        $this->assertSame('2.90', (string) $order->delivery_fee_eur);
        $this->assertSame('14.90', (string) $order->total_amount_eur);
        $this->assertNull($order->pickup_location);
    }

    public function test_overbooking_past_remaining_stock_is_rejected_at_request_level(): void
    {
        $this->from(route('public.shop.index'))
            ->post(route('public.shop.store'), $this->pickupPayload([
                'quantities' => [$this->product->id => 25], // only 10 in stock
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_create_order_service_rejects_overbooking_race_tight(): void
    {
        $service = app(ShopOrderService::class);

        $this->expectException(InsufficientStockException::class);

        $service->createOrder([
            'name' => 'Greedy',
            'email' => 'greedy@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'items' => [['product_id' => $this->product->id, 'quantity' => 50.0]],
        ]);

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_delivery_requires_address_and_phone(): void
    {
        $this->from(route('public.shop.index'))
            ->post(route('public.shop.store'), $this->pickupPayload([
                'fulfillment_mode' => 'delivery',
                'pickup_location' => null,
                // no phone, no address
            ]))
            ->assertSessionHasErrors(['phone', 'delivery_street', 'delivery_postal_code', 'delivery_city']);

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_pickup_requires_known_pickup_location(): void
    {
        $this->from(route('public.shop.index'))
            ->post(route('public.shop.store'), $this->pickupPayload([
                'pickup_location' => 'somewhere_else',
            ]))
            ->assertSessionHasErrors('pickup_location');

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_consent_is_required(): void
    {
        $this->from(route('public.shop.index'))
            ->post(route('public.shop.store'), $this->pickupPayload(['consent' => null]))
            ->assertSessionHasErrors('consent');

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_date_not_on_the_inventory_menu_is_rejected(): void
    {
        $this->from(route('public.shop.index'))
            ->post(route('public.shop.store'), $this->pickupPayload([
                'expected_delivery_date' => '2026-06-06', // no stock that day
            ]))
            ->assertSessionHasErrors('expected_delivery_date');

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_pay_initiates_checkout_with_total_amount_and_renders_widget(): void
    {
        $order = ShopOrder::factory()->create([
            'total_amount_eur' => 12.00,
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createOneTimeCheckout')
            ->once()
            ->withArgs(function (float $amount, string $reference, string $description, ?string $customerId, array $extras) use ($order) {
                return $amount === 12.00
                    && str_contains($reference, 'shop-'.$order->id.'-')
                    && $description === 'Erdgut Shop-Bestellung'
                    && $customerId === null
                    && isset($extras['redirect_url'])
                    && str_contains($extras['redirect_url'], '/shop/'.$order->id.'/return');
            })
            ->andReturn((object) ['id' => 'co_test_shop_abc']);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.pay', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee('co_test_shop_abc')
            ->assertSee(__('shop.pay_heading'));

        $this->assertSame('co_test_shop_abc', $order->fresh()->sumup_checkout_id);
    }

    public function test_return_marks_order_paid_and_queues_confirmation_when_sumup_confirms(): void
    {
        Mail::fake();

        $order = ShopOrder::factory()->create([
            'email' => 'paid@example.com',
            'sumup_checkout_id' => 'co_pending_xyz',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_pending_xyz')
            ->andReturn((object) [
                'status' => 'PAID',
                'transactions' => [(object) ['id' => 'pay_test_xyz']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('shop.return_paid_heading'));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pay_test_xyz', $order->sumup_payment_id);

        Mail::assertQueued(ShopOrderConfirmationMail::class, fn ($mail) => $mail->hasTo('paid@example.com'));
    }

    public function test_return_marks_order_failed_when_sumup_reports_failure(): void
    {
        Mail::fake();

        $order = ShopOrder::factory()->create([
            'sumup_checkout_id' => 'co_dead_xyz',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_dead_xyz')
            ->andReturn((object) ['status' => 'FAILED', 'transactions' => []]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('shop.return_failed_heading'));

        $this->assertSame(ShopOrderStatus::Failed, $order->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_pay_for_failed_order_resets_to_pending_and_creates_fresh_checkout(): void
    {
        $order = ShopOrder::factory()->create([
            'sumup_checkout_id' => 'co_old_dead',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::Failed,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createOneTimeCheckout')
            ->once()
            ->andReturn((object) ['id' => 'co_fresh_retry']);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.pay', ['shopOrder' => $order->id]))->assertOk();

        $order->refresh();
        $this->assertSame(ShopOrderStatus::PendingPayment, $order->status);
        $this->assertSame('co_fresh_retry', $order->sumup_checkout_id);
    }

    public function test_pay_in_person_pickup_skips_sumup_and_queues_confirmation(): void
    {
        Mail::fake();

        $response = $this->post(route('public.shop.store'), $this->pickupPayload([
            'email' => 'inperson@example.com',
            'payment_method' => 'in_person',
        ]));

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertSame('in_person', $order->payment_method);
        $this->assertSame(ShopOrderStatus::PendingPayment, $order->status);

        $response->assertRedirect(route('public.shop.return', ['shopOrder' => $order->id]));

        Mail::assertQueued(ShopOrderConfirmationMail::class, fn ($mail) => $mail->hasTo('inperson@example.com'));
    }

    public function test_pay_in_person_with_delivery_is_rejected(): void
    {
        $this->from(route('public.shop.index'))
            ->post(route('public.shop.store'), $this->pickupPayload([
                'fulfillment_mode' => 'delivery',
                'pickup_location' => null,
                'phone' => '+49 30 12345678',
                'delivery_street' => 'Musterstr. 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
                'payment_method' => 'in_person',
            ]))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_marketing_header_renders_shop_link(): void
    {
        // The shop link lives in the shared marketing customer-header,
        // rendered on every marketing page (e.g. the public catalogue).
        $this->get(route('public.products.index'))
            ->assertOk()
            ->assertSee(route('public.shop.index'))
            ->assertSee(__('Shop'));
    }
}
