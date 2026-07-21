<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Mail\ShopOrderConfirmationMail;
use App\Mail\ShopOrderFinalizedAdminNotification;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Shop\CheckoutDraft;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ShopOrderService;
use App\Support\Money;
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

    public function test_storefront_does_not_show_product_card_description(): void
    {
        $note = $this->product->cardDescription();
        $this->assertNotSame('', $note, 'Factory product should have a card description to assert against.');

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertDontSee($note, escape: false);
    }

    public function test_storefront_shows_at_most_three_taste_profile_tags(): void
    {
        $tagged = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 5.00,
            'taste_profile_tags' => ['sweet', 'crisp', 'earthy', 'fresh'],
            'custom_taste_tags' => [
                ['de' => 'Waldig', 'en' => 'Woody', 'fr' => 'Boisé'],
            ],
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $tagged->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 10,
            'freshness_window_days' => 1,
        ]);

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($tagged->name)
            ->assertSee('Sweet', escape: false)
            ->assertSee('Crisp', escape: false)
            ->assertSee('Earthy', escape: false)
            // Scope to the tag-chip render pattern (see substr_count below) so
            // the assertion isn't tripped by the same word appearing elsewhere,
            // e.g. "Fresh" in the shop meta description.
            ->assertDontSee('>Fresh<', escape: false)
            ->assertDontSee('>Woody<', escape: false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '>Sweet<'));
        $this->assertSame(1, substr_count($html, '>Crisp<'));
        $this->assertSame(1, substr_count($html, '>Earthy<'));
    }

    public function test_shop_terms_page_renders(): void
    {
        $this->get(route('public.shop.terms'))
            ->assertOk()
            ->assertSee(__('shop_terms.heading'))
            ->assertSee(__('shop_terms.section_withdrawal_heading'));
    }

    public function test_imprint_page_renders_provider_details(): void
    {
        config(['shop.company_vat_id' => 'DE123456789']);

        $this->get(route('public.imprint'))
            ->assertOk()
            ->assertSee('Erdgut UG (haftungsbeschränkt)')
            ->assertSee('HRB 286220 B')
            ->assertSee('Arthur Hamcha')
            ->assertSee('DE123456789');
    }

    public function test_storefront_footer_links_to_imprint(): void
    {
        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertSee('href="'.route('public.imprint').'"', false);
    }

    public function test_storefront_consent_links_to_shop_terms_not_box_terms(): void
    {
        // Consent has moved off the storefront onto the checkout review page
        // (step 3 of the new wizard). Seed a complete draft so we can render it.
        session()->put(CheckoutDraft::KEY, [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'expected_delivery_date' => self::DELIVERY_DATE,
            'fulfillment_mode' => 'pickup',
            'payment_method' => 'online',
            'pickup_location' => 'bornsdorf',
            'name' => 'Anna Tester',
            'email' => 'a@example.com',
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->get(route('public.shop.checkout.review'))
            ->assertOk()
            ->assertSee('href="'.route('public.shop.terms').'"', false)
            ->assertDontSee('href="'.route('public.terms').'"', false);
    }

    public function test_storefront_omits_products_with_no_stock_in_the_week(): void
    {
        // A second product with NO stock anywhere in the week must be absent
        // entirely (not even greyed-out).
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

    public function test_storefront_shows_week_products_greyed_when_unavailable_on_selected_day(): void
    {
        // DELIVERY_DATE 2026-06-05 (Fri) sits in the Mon 06-01 .. Sun 06-07
        // week. A product stocked only on Wed 06-03 of that week must appear,
        // but greyed-out (disabled picker) with a note pointing at 06-03 —
        // not orderable for the Friday, yet discoverable.
        $weekOther = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 3.00,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $weekOther->id,
            'received_on' => '2026-06-03',
            'quantity' => 5,
            'freshness_window_days' => 1,
        ]);

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($weekOther->name)
            ->assertSee(__('shop.weekly_unavailable_label'))
            ->getContent();

        // It is shown as an unavailable card with a disabled picker — there
        // must be no active quantity field bound to its id.
        $this->assertStringContainsString('data-testid="shop-product-card-unavailable"', $html);
        $this->assertStringNotContainsString('name="quantities['.$weekOther->id.']"', $html);

        // The orderable product still has an active quantity stepper.
        $this->assertStringContainsString('name="quantities['.$this->product->id.']"', $html);
        $this->assertStringContainsString('data-testid="shop-qty-stepper"', $html);
    }

    public function test_storefront_shows_mass_product_price_in_per_100g_unit(): void
    {
        $mass = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => '100g',
            'unit_price' => 0.0215, // €/g → €2,15 per 100 g
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $mass->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 500,
            'freshness_window_days' => 1,
        ]);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($mass->displayPrice(), escape: false);
    }

    public function test_mass_product_quantity_picker_steps_by_50_grams(): void
    {
        $mass = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.01, // per gram
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $mass->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 300, // 300 g in stock
            'freshness_window_days' => 1,
        ]);

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();

        // The stepper ladder (Alpine @js registry) offers 50 g multiples:
        // 50 and 100 present; 75 (off-step) absent. Blade's @js escapes JSON
        // quotes to \u0022, so each value pair reads \u0022v\u0022:50.
        $this->assertStringContainsString('\u0022v\u0022:50', $html);
        $this->assertStringContainsString('\u0022v\u0022:100', $html);
        $this->assertStringNotContainsString('\u0022v\u0022:75', $html);
        // Capped at remaining stock (300 g) — 350 not offered.
        $this->assertStringNotContainsString('\u0022v\u0022:350', $html);
    }

    public function test_off_step_mass_quantity_is_rejected(): void
    {
        $mass = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.01,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $mass->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 1000,
            'freshness_window_days' => 1,
        ]);

        // 75 g is not a multiple of the 50 g step → rejected.
        $this->post(route('public.shop.store'), $this->pickupPayload([
            'quantities' => [$mass->id => 75],
        ]))->assertSessionHasErrors('items.0.quantity');

        // 100 g is a clean multiple → accepted.
        $this->post(route('public.shop.store'), $this->pickupPayload([
            'quantities' => [$mass->id => 100],
        ]))->assertSessionHasNoErrors();
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

    public function test_pay_page_renders_variant_name_next_to_product_name(): void
    {
        $order = ShopOrder::factory()->create([
            'total_amount_eur' => 8.00,
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'variant_id' => null,
            'variant_name_snapshot' => 'YDA',
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createOneTimeCheckout')->andReturn((object) ['id' => 'co_variant_pay']);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.pay', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee('YDA', escape: false);
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
            ->assertSee(__('shop.return_basket_heading'));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pay_test_xyz', $order->sumup_payment_id);

        Mail::assertQueued(ShopOrderConfirmationMail::class, fn ($mail) => $mail->hasTo('paid@example.com'));
    }

    public function test_return_marks_order_paid_when_transaction_succeeded_but_checkout_status_lags(): void
    {
        Mail::fake();

        // Regression: SumUp redirected the customer back while the checkout
        // `status` still read PENDING, even though the payment had already
        // settled (a SUCCESSFUL transaction is present). The order must be
        // marked paid instead of leaving the customer stuck on "verifying".
        $order = ShopOrder::factory()->create([
            'email' => 'lagging@example.com',
            'sumup_checkout_id' => 'co_lagging_status',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_lagging_status')
            ->andReturn((object) [
                'status' => 'PENDING',
                'transactions' => [(object) ['id' => 'pay_lagging_xyz', 'status' => 'SUCCESSFUL']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('shop.return_basket_heading'));

        $order->refresh();
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pay_lagging_xyz', $order->sumup_payment_id);

        Mail::assertQueued(ShopOrderConfirmationMail::class, fn ($mail) => $mail->hasTo('lagging@example.com'));
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

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee(Money::formatEur($order->total_amount_eur), false);

        Mail::assertQueued(ShopOrderConfirmationMail::class, fn ($mail) => $mail->hasTo('inperson@example.com'));
    }

    public function test_confirmation_email_subject_is_localized_not_a_raw_key(): void
    {
        $inPerson = ShopOrder::factory()->create(['payment_method' => 'in_person']);
        $paid = ShopOrder::factory()->create(['payment_method' => 'online']);

        $inPersonSubject = (new ShopOrderConfirmationMail($inPerson))->envelope()->subject;
        $paidSubject = (new ShopOrderConfirmationMail($paid))->envelope()->subject;

        // Regression: the keys were missing, so __() echoed the raw key.
        $this->assertStringNotContainsString('shop.email_subject', (string) $inPersonSubject);
        $this->assertStringNotContainsString('shop.email_subject', (string) $paidSubject);
        $this->assertSame(__('shop.email_subject_in_person'), $inPersonSubject);
        $this->assertSame(__('shop.email_subject_paid'), $paidSubject);
    }

    public function test_admin_notification_shows_coupon_discount_line(): void
    {
        // Regression: the admin email listed the full delivery fee but no
        // discount line, so the rows didn't reconcile to the total when a
        // free-delivery (or any) coupon was applied.
        $order = ShopOrder::factory()->create([
            'fulfillment_mode' => 'delivery',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::Paid,
            'paid_at' => now(),
            'items_total_eur' => 1.95,
            'delivery_fee_eur' => 2.90,
            'discount_eur' => 2.90,
            'coupon_code' => 'FREESHIP',
            'total_amount_eur' => 1.95,
        ]);

        $html = (new ShopOrderFinalizedAdminNotification($order))->render();

        $this->assertStringContainsString('Discount', $html);
        $this->assertStringContainsString('FREESHIP', $html);
        $this->assertStringContainsString('− €2.90', $html);
    }

    public function test_in_person_order_notifies_admins_on_checkout(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@erdgut.test']);

        $this->post(route('public.shop.store'), $this->pickupPayload([
            'payment_method' => 'in_person',
        ]))->assertRedirect();

        // In-person checkout is a confirmed sale → admins are pinged right away.
        Mail::assertQueued(
            ShopOrderFinalizedAdminNotification::class,
            fn ($mail) => $mail->hasTo('admin@erdgut.test'),
        );
    }

    public function test_online_payment_confirmation_notifies_admins(): void
    {
        Mail::fake();
        User::factory()->admin()->create(['email' => 'admin@erdgut.test']);

        $order = ShopOrder::factory()->create([
            'sumup_checkout_id' => 'co_pending_admin',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_pending_admin')
            ->andReturn((object) [
                'status' => 'PAID',
                'transactions' => [(object) ['id' => 'pay_admin_xyz']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))->assertOk();

        Mail::assertQueued(
            ShopOrderFinalizedAdminNotification::class,
            fn ($mail) => $mail->hasTo('admin@erdgut.test'),
        );
    }

    public function test_abandoned_online_checkout_does_not_notify_admins(): void
    {
        Mail::fake();
        User::factory()->admin()->create(['email' => 'admin@erdgut.test']);

        // Guest finishes the form and is sent to SumUp, but never pays — the
        // order sits PendingPayment + online. Admins must NOT be pinged.
        $this->post(route('public.shop.store'), $this->pickupPayload([
            'payment_method' => 'online',
        ]))->assertRedirect();

        Mail::assertNotQueued(ShopOrderFinalizedAdminNotification::class);
    }

    public function test_finalized_notification_targets_admins_only(): void
    {
        Mail::fake();
        User::factory()->admin()->create(['email' => 'admin@erdgut.test']);
        User::factory()->create(['is_admin' => false, 'email' => 'customer@erdgut.test']);

        $this->post(route('public.shop.store'), $this->pickupPayload([
            'payment_method' => 'in_person',
        ]))->assertRedirect();

        Mail::assertNotSent(
            ShopOrderFinalizedAdminNotification::class,
            fn ($mail) => $mail->hasTo('customer@erdgut.test'),
        );
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

    public function test_pay_reuses_existing_checkout_on_reload(): void
    {
        // Regression: hitting /pay a second time (reload, back-navigation)
        // used to create a brand-new SumUp checkout and overwrite the DB,
        // orphaning the checkout the widget already loaded. Now /pay reuses
        // the stored checkout ID when one is already present.
        $order = ShopOrder::factory()->create([
            'sumup_checkout_id' => 'co_already_exists',
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        // createOneTimeCheckout must NOT be called — we reuse the existing ID.
        $sumUp->shouldNotReceive('createOneTimeCheckout');
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.shop.pay', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee('co_already_exists');

        // The stored checkout ID must remain unchanged.
        $this->assertSame('co_already_exists', $order->fresh()->sumup_checkout_id);
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

    // ── Per-product quantity step ─────────────────────────────────────────

    /**
     * A mass product with a custom 500 g step should reject a quantity
     * that is not a multiple of that step.
     */
    public function test_quantity_not_a_multiple_of_custom_step_is_rejected(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.02,    // €/g
            'quantity_step' => 500,  // only 500 g / 1 kg / 1.5 kg … allowed
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 3000,
            'freshness_window_days' => 1,
        ]);

        // 250 g is not a multiple of 500 — should fail validation.
        $response = $this->post(route('public.shop.store'), array_merge(
            $this->pickupPayload(),
            ['quantities' => [$product->id => 250]]
        ));

        $response->assertSessionHasErrors();
    }

    /**
     * A mass product with a custom 500 g step should accept a quantity
     * that is an exact multiple of that step.
     */
    public function test_quantity_that_is_multiple_of_custom_step_is_accepted(): void
    {
        Mail::fake();

        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.02,    // €/g
            'quantity_step' => 500,
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 3000,
            'freshness_window_days' => 1,
        ]);

        // 1000 g = 2 × 500 — should pass.
        $response = $this->post(route('public.shop.store'), array_merge(
            $this->pickupPayload(),
            ['quantities' => [$product->id => 1000]]
        ));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_orders', ['email' => 'anna@example.com']);
    }

    /**
     * When quantity_step is null, the product falls back to the global
     * config default and accepts multiples of that default step.
     */
    public function test_null_quantity_step_falls_back_to_global_config(): void
    {
        Mail::fake();
        config(['shop.quantity_step' => 100]);

        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.02,
            'quantity_step' => null, // use global default = 100
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 3000,
            'freshness_window_days' => 1,
        ]);

        // 300 g = 3 × 100 — should pass.
        $response = $this->post(route('public.shop.store'), array_merge(
            $this->pickupPayload(),
            ['quantities' => [$product->id => 300]]
        ));

        $response->assertSessionHasNoErrors();
    }

    public function test_in_person_duplicate_submit_redirects_to_existing_order(): void
    {
        Mail::fake();

        $payload = $this->pickupPayload(['payment_method' => 'in_person']);

        // First submit — creates the order.
        $first = $this->post(route('public.shop.store'), $payload);
        $firstOrder = ShopOrder::query()->where('payment_method', 'in_person')->latest('id')->first();
        $first->assertRedirect(route('public.shop.return', ['shopOrder' => $firstOrder->id]));

        // Second submit within 10 minutes — must NOT create a new order.
        $countBefore = ShopOrder::query()->count();
        $second = $this->post(route('public.shop.store'), $payload);
        $this->assertSame($countBefore, ShopOrder::query()->count(), 'Duplicate in-person order was created.');
        $second->assertRedirect(route('public.shop.return', ['shopOrder' => $firstOrder->id]));
    }

    public function test_in_person_dedup_does_not_block_after_ten_minutes(): void
    {
        Mail::fake();

        $payload = $this->pickupPayload(['payment_method' => 'in_person']);

        // Seed a stale in-person order directly (11 minutes old) — bypasses store().
        $staleOrder = ShopOrder::factory()->create([
            'email' => $payload['email'],
            'expected_delivery_date' => $payload['expected_delivery_date'],
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
            'created_at' => now()->subMinutes(11),
        ]);

        // Submit now — dedup window expired, so a new order should be created.
        $countBefore = ShopOrder::query()->count();
        $this->post(route('public.shop.store'), $payload);
        $this->assertSame($countBefore + 1, ShopOrder::query()->count(), 'Expected a new order after dedup window expired.');
    }

    public function test_storefront_step_one_points_to_checkout_start_route(): void
    {
        // The legacy "in-person confirmation modal" lived on /shop and is now
        // gone — the wizard's review page (step 3) is where consent and the
        // in-person confirmation happen. /shop only has to render the
        // products + basket plus a submit button that POSTs to /shop/checkout/start.
        $response = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]));
        $response->assertOk();
        $response->assertSee('action="'.route('public.shop.checkout.start').'"', false);
        $response->assertSee('data-testid="shop-checkout-progress"', false);
        $response->assertSee('data-testid="shop-submit"', false);
    }

    public function test_storefront_does_not_call_undefined_alpine_methods(): void
    {
        // Regression: the picker subtotal used `formatMoney(pickerSubtotal)`
        // for a stretch, but the actual storefront helper is `money()`. The
        // misnomer threw a ReferenceError every time the picker subtotal
        // re-evaluated, which broke Alpine reactivity downstream (the
        // canonical items[] template + the basket chip occasionally stopped
        // updating). Guard against the typo creeping back in.
        $html = (string) $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('formatMoney(', $html, 'formatMoney() is not defined on the storefront Alpine component — use money() instead.');
    }

    public function test_storefront_renders_basket_chip_and_canonical_items_template(): void
    {
        $response = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]));
        $response->assertOk();
        // Floating basket chip is in the markup (it shows only when
        // the basket is non-empty, but the element ships either way).
        $response->assertSee('data-testid="shop-basket-chip"', false);
        // Canonical cart payload — the basket emits items[i][product_id/variant_id/quantity]
        // hidden inputs that the backend prefers over the legacy two-map shape.
        $response->assertSee('items[${i}][product_id]', false);
        $response->assertSee('items[${i}][quantity]', false);
    }

    public function test_storefront_does_not_show_product_from_b2b_only_inflow_alone(): void
    {
        $b2bOnly = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 6.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $b2bOnly->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 20,
            'freshness_window_days' => 1,
            'b2b_only' => true,
        ]);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertDontSee($b2bOnly->name);
    }
}
