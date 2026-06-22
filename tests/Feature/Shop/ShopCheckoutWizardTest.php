<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Services\Shop\CheckoutDraft;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * End-to-end coverage of the three-step storefront checkout wizard:
 *
 *   GET  /shop                           → step 1, products + basket
 *   POST /shop/checkout/start            → seed draft → /shop/checkout/details
 *   GET  /shop/checkout/details          → step 2, details form
 *   POST /shop/checkout/details          → merge into draft → /shop/checkout/review
 *   GET  /shop/checkout/review           → step 3, read-only review + consent
 *   POST /shop/checkout/place            → create order → /shop/{order}/pay
 *
 * The route guards (basket missing / details missing / date no longer on
 * the menu / draft expired) are asserted alongside the happy path so a
 * customer who jumps mid-flow always lands on a sensible page.
 */
class ShopCheckoutWizardTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));

        $producer = Producer::factory()->create();
        $this->product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);
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

    /** @return array<string, mixed> */
    private function basketPayload(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
            'expected_delivery_date' => self::DELIVERY_DATE,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function detailsPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Frieda Tester',
            'email' => 'frieda@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
        ], $overrides);
    }

    private function seedBasket(): void
    {
        session()->put(CheckoutDraft::KEY, [
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
            'expected_delivery_date' => self::DELIVERY_DATE,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function seedFullDraft(array $overrides = []): void
    {
        session()->put(CheckoutDraft::KEY, array_merge([
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
            'expected_delivery_date' => self::DELIVERY_DATE,
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'name' => 'Frieda Tester',
            'email' => 'frieda@example.com',
            'updated_at' => now()->toIso8601String(),
        ], $overrides));
    }

    // ── Step 1 → 2: POST /shop/checkout/start ─────────────────────────

    public function test_start_seeds_draft_and_redirects_to_details(): void
    {
        $this->post(route('public.shop.checkout.start'), $this->basketPayload())
            ->assertRedirect(route('public.shop.checkout.details'));

        $draft = (array) session(CheckoutDraft::KEY);
        $this->assertSame(self::DELIVERY_DATE, $draft['expected_delivery_date']);
        $this->assertSame($this->product->id, $draft['items'][0]['product_id']);
        $this->assertSame(3, $draft['items'][0]['quantity']);
    }

    public function test_start_rejects_empty_basket(): void
    {
        $this->post(route('public.shop.checkout.start'), [
            'expected_delivery_date' => self::DELIVERY_DATE,
        ])
            ->assertSessionHasErrors(['items']);
    }

    public function test_start_rejects_date_not_on_the_menu(): void
    {
        $this->post(route('public.shop.checkout.start'), $this->basketPayload([
            'expected_delivery_date' => '2026-08-30',
        ]))
            ->assertSessionHasErrors(['expected_delivery_date']);
    }

    // ── Step 2: GET /shop/checkout/details ────────────────────────────

    public function test_details_redirects_to_shop_when_basket_missing(): void
    {
        $this->get(route('public.shop.checkout.details'))
            ->assertRedirect(route('public.shop.index'));
    }

    public function test_details_renders_when_basket_present(): void
    {
        $this->seedBasket();

        $this->get(route('public.shop.checkout.details'))
            ->assertOk()
            ->assertSee('data-testid="shop-checkout-details-form"', false)
            ->assertSee('data-testid="shop-checkout-progress"', false)
            ->assertSee('data-testid="shop-checkout-summary"', false)
            ->assertSee($this->product->name);
    }

    // ── Step 2 → 3: POST /shop/checkout/details ───────────────────────

    public function test_details_store_merges_into_draft_and_redirects(): void
    {
        $this->seedBasket();

        $this->post(route('public.shop.checkout.details.store'), $this->detailsPayload([
            'name' => 'Maja Beispiel',
            'email' => 'maja@example.com',
        ]))
            ->assertRedirect(route('public.shop.checkout.review'));

        $draft = (array) session(CheckoutDraft::KEY);
        $this->assertSame('Maja Beispiel', $draft['name']);
        $this->assertSame('maja@example.com', $draft['email']);
        // Basket survived the merge.
        $this->assertSame($this->product->id, $draft['items'][0]['product_id']);
    }

    public function test_details_store_returns_validation_errors_for_missing_pickup(): void
    {
        $this->seedBasket();

        $this->from(route('public.shop.checkout.details'))
            ->post(route('public.shop.checkout.details.store'), $this->detailsPayload([
                'pickup_location' => null,
            ]))
            ->assertRedirect(route('public.shop.checkout.details'))
            ->assertSessionHasErrors(['pickup_location']);
    }

    public function test_details_store_requires_delivery_address_when_delivery_selected(): void
    {
        $this->seedBasket();

        $this->from(route('public.shop.checkout.details'))
            ->post(route('public.shop.checkout.details.store'), $this->detailsPayload([
                'fulfillment_mode' => 'delivery',
                'pickup_location' => null,
                'payment_method' => 'online',
            ]))
            ->assertRedirect(route('public.shop.checkout.details'))
            ->assertSessionHasErrors(['delivery_street', 'delivery_postal_code', 'delivery_city', 'phone']);
    }

    public function test_details_store_blocks_in_person_with_delivery(): void
    {
        $this->seedBasket();

        $this->from(route('public.shop.checkout.details'))
            ->post(route('public.shop.checkout.details.store'), $this->detailsPayload([
                'fulfillment_mode' => 'delivery',
                'pickup_location' => null,
                'payment_method' => 'in_person',
                'delivery_street' => 'Schönhauser Allee 1',
                'delivery_postal_code' => '10119',
                'delivery_city' => 'Berlin',
                'phone' => '030 1234567',
            ]))
            ->assertSessionHasErrors(['payment_method']);
    }

    // ── Step 3: GET /shop/checkout/review ────────────────────────────

    public function test_review_redirects_to_details_when_details_missing(): void
    {
        $this->seedBasket();

        $this->get(route('public.shop.checkout.review'))
            ->assertRedirect(route('public.shop.checkout.details'));
    }

    public function test_review_renders_full_summary(): void
    {
        $this->seedFullDraft();

        $response = $this->get(route('public.shop.checkout.review'));

        $response->assertOk()
            ->assertSee('data-testid="shop-checkout-place"', false)
            ->assertSee('data-testid="shop-checkout-progress"', false)
            ->assertSee('data-testid="shop-checkout-consent"', false)
            ->assertSee($this->product->name)
            ->assertSee('Frieda Tester')
            ->assertSee('frieda@example.com');
    }

    // ── Place: POST /shop/checkout/place ─────────────────────────────

    public function test_place_creates_order_and_redirects_to_pay_when_online(): void
    {
        Mail::fake();
        $sumUp = Mockery::mock(SumUpService::class);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->seedFullDraft();

        $response = $this->post(route('public.shop.checkout.place'), ['consent' => '1']);

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('public.shop.pay', ['shopOrder' => $order->id]));
        $this->assertSame(ShopOrderStatus::PendingPayment, $order->status);
        $this->assertSame('frieda@example.com', $order->email);
        $this->assertSame(1, $order->items->count());
        $this->assertSame($this->product->id, $order->items->first()->product_id);

        // Draft is cleared after a successful create.
        $this->assertNull(session(CheckoutDraft::KEY));
    }

    public function test_place_requires_consent(): void
    {
        $this->seedFullDraft();

        $this->from(route('public.shop.checkout.review'))
            ->post(route('public.shop.checkout.place'), [])
            ->assertRedirect(route('public.shop.checkout.review'))
            ->assertSessionHasErrors(['consent']);

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_place_in_person_pickup_redirects_to_return_and_queues_mail(): void
    {
        Mail::fake();

        $this->seedFullDraft([
            'payment_method' => 'in_person',
        ]);

        $response = $this->post(route('public.shop.checkout.place'), ['consent' => '1']);

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('public.shop.return', ['shopOrder' => $order->id]));
        $this->assertSame('in_person', $order->payment_method);
        Mail::assertQueued(ShopOrderConfirmationMail::class);
    }

    public function test_place_clears_draft_and_bounces_to_shop_when_date_falls_off_menu(): void
    {
        $this->seedFullDraft([
            'expected_delivery_date' => '2026-08-30', // far outside the inventory horizon
        ]);

        $this->post(route('public.shop.checkout.place'), ['consent' => '1'])
            ->assertRedirect(route('public.shop.index'))
            ->assertSessionHas('error');

        $this->assertNull(session(CheckoutDraft::KEY));
        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_place_redirects_to_shop_when_draft_missing(): void
    {
        $this->post(route('public.shop.checkout.place'), ['consent' => '1'])
            ->assertRedirect(route('public.shop.index'));
    }
}
