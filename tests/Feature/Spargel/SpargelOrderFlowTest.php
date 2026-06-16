<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Mail\SpargelOrderConfirmationMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\SpargelOrder;
use App\Services\SpargelOrderService;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * Trial-shop end-to-end coverage for the standalone spargel sales channel.
 *
 * The entry view (GET /spargel) is retired and now redirects to /shop; the
 * remaining checkout mechanics — submit (POST /spargel) → SumUp pay
 * (GET /spargel/{order}/pay) → SumUp redirect (GET /spargel/{order}/return)
 * — stay live so any in-flight SpargelOrder can still complete.
 *
 * Tests assert at each hop:
 * - the right status transitions happen on the SpargelOrder row,
 * - SumUpService is called with the right amount/reference/return-url,
 * - the public surface stays decoupled from User/Order/Subscription.
 */
class SpargelOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Monday used by every order-create POST in this suite. setUp() creates
     * ProductWeekStock rows for this date (and the two Mondays after it)
     * with generous stock so per-week cap validation doesn't trip on basic
     * cases. Tests that want to exercise the cap override the stock.
     */
    private const DEFAULT_DELIVERY_MONDAY = '2026-05-04';

    /**
     * Wire the spargel-products-and-stock scaffold once per test. Phase 3
     * gates order-create on `expected_delivery_date` being a Monday that
     * appears in `availableWeeks()`, which means we need real `Product`
     * rows (unit=g) and `ProductWeekStock` rows present in every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Lock "now" to the Tuesday before DEFAULT_DELIVERY_MONDAY so all
        // three seeded Mondays are in the future from the suite's POV.
        // Individual tests that need a different "now" override via their
        // own Carbon::setTestNow(); the tearDown null-resets it.
        Carbon::setTestNow(Carbon::parse('2026-04-28 10:00:00'));

        $producer = Producer::factory()->create();

        // Spargel products are mass-kind, canonical = grams. Store
        // €/g (= 21.50 / 1000 = 0.0215) so the per-kilogram display
        // round-trips to €21,50/kg.
        $white = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'unit_price' => 0.0215,
        ]);
        $green = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'unit_price' => 0.0215,
        ]);

        config([
            'spargel.white_product_id' => $white->id,
            'spargel.green_product_id' => $green->id,
        ]);

        // 5 kg each for 3 future Mondays — plenty for every default-payload
        // order in this suite. Freshness=7 so a single Monday inflow
        // covers the whole week (matches the legacy weekly-stock semantics
        // before the daily-inventory rebuild). Individual tests that
        // exercise the per-date cap shrink these on demand.
        foreach ([self::DEFAULT_DELIVERY_MONDAY, '2026-05-11', '2026-05-18'] as $monday) {
            ProductStockInflow::create([
                'product_id' => $white->id,
                'received_on' => $monday,
                'quantity' => 5000,
                'freshness_window_days' => 7,
            ]);
            ProductStockInflow::create([
                'product_id' => $green->id,
                'received_on' => $monday,
                'quantity' => 5000,
                'freshness_window_days' => 7,
            ]);
        }
    }

    public function test_spargel_entry_redirects_to_shop(): void
    {
        // The spargel channel is retired: its entry view now redirects to
        // the general shop so bookmarked /spargel links still land somewhere.
        $this->get(route('public.spargel.show'))
            ->assertRedirect(route('public.shop.index'));
    }

    public function test_overbooking_a_week_is_rejected_per_variety(): void
    {
        // Shrink white stock for 2026-05-04 to 200 g.
        ProductStockInflow::query()
            ->where('product_id', config('spargel.white_product_id'))
            ->whereDate('received_on', self::DEFAULT_DELIVERY_MONDAY)
            ->update(['quantity' => 200]);

        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Greedy',
                'email' => 'greedy@example.com',
                'quantity_green_grams' => '0',
                'quantity_white_grams' => '500', // > 200 g remaining
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_white_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_friday_order_can_target_next_monday_without_cutoff_shift(): void
    {
        // Phase 3 removed the time-based cutoff entirely. On Friday the
        // customer can still pick the very next Monday — no auto-shift.
        Carbon::setTestNow(Carbon::parse('2026-05-01 22:00:00')); // Fri night

        try {
            $this->post(route('public.spargel.store'), [
                'name' => 'Late Friday',
                'email' => 'late@example.com',
                'quantity_green_grams' => '200',
                'quantity_white_grams' => '300',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])->assertRedirect();

            $order = SpargelOrder::query()->latest('id')->first();
            $this->assertSame(self::DEFAULT_DELIVERY_MONDAY, $order->expected_delivery_date->toDateString());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_landing_page_renders_link_to_shop(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-testid="shop-home-cta-shop"', false)
            ->assertSee(route('public.shop.index'));
    }

    public function test_submitting_valid_green_only_pickup_creates_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-28 10:00:00')); // Tue

        try {
            $response = $this->post(route('public.spargel.store'), [
                'name' => 'Anna Mustermann',
                'email' => 'anna@example.com',
                'phone' => '+49 30 12345678',
                'quantity_green_grams' => '300',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ]);

            $order = SpargelOrder::query()->latest('id')->first();
            $this->assertNotNull($order);
            $this->assertSame('Anna Mustermann', $order->name);
            $this->assertSame(300, $order->quantity_green_grams);
            $this->assertSame(0, $order->quantity_white_grams);
            $this->assertSame('pickup', $order->fulfillment_mode);
            $this->assertSame('0.00', (string) $order->delivery_fee_eur);
            // 300 g * 21.50 €/kg = 6.45
            $this->assertSame('6.45', (string) $order->total_amount_eur);
            $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);
            $this->assertSame('2026-05-04', $order->expected_delivery_date->toDateString());

            $response->assertRedirect(route('public.spargel.pay', ['spargelOrder' => $order->id]));
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_submitting_mixed_green_and_white_creates_order_with_both_quantities(): void
    {
        $this->post(route('public.spargel.store'), [
            'name' => 'Mira Mixed',
            'email' => 'mira@example.com',
            'phone' => '+49 30 99887766',
            'quantity_green_grams' => '500',
            'quantity_white_grams' => '500',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Musterstraße 12',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(500, $order->quantity_green_grams);
        $this->assertSame(500, $order->quantity_white_grams);
        // 1000 g * 21.50 = 21.50; +2.90 delivery = 24.40
        $this->assertSame('24.40', (string) $order->total_amount_eur);
        $this->assertSame('Musterstraße 12', $order->delivery_street);
    }

    public function test_submitting_white_only_delivery_creates_order(): void
    {
        $this->post(route('public.spargel.store'), [
            'name' => 'Bernd Liefert',
            'email' => 'bernd@example.com',
            'phone' => '+49 30 99887766',
            'quantity_green_grams' => '0',
            'quantity_white_grams' => '2000',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Musterstraße 12',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('delivery', $order->fulfillment_mode);
        $this->assertSame(0, $order->quantity_green_grams);
        $this->assertSame(2000, $order->quantity_white_grams);
        $this->assertSame('2.90', (string) $order->delivery_fee_eur);
        // 2000 g * 21.50 + 2.90 = 45.90
        $this->assertSame('45.90', (string) $order->total_amount_eur);
    }

    public function test_quantity_must_be_a_multiple_of_50_grams(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Quentin',
                'email' => 'quentin@example.com',
                'quantity_green_grams' => '375', // not a multiple of 50
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_green_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_quantity_above_max_is_rejected(): void
    {
        // 5 kg of one variety is over the per-variety ceiling (3 kg).
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Wholesale',
                'email' => 'wholesale@example.com',
                'quantity_green_grams' => '0',
                'quantity_white_grams' => '5000',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_white_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_combined_total_below_minimum_is_rejected(): void
    {
        // 50 + 0 = 50 g — below QUANTITY_COMBINED_MIN_GRAMS (100 g).
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Tiny',
                'email' => 'tiny@example.com',
                'quantity_green_grams' => '50',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_green_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_combined_total_above_max_is_rejected(): void
    {
        // 1500 + 1600 = 3100 g — over combined max (3000 g) even
        // though each per-variety value is fine on its own.
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'TooMuch',
                'email' => 'toomuch@example.com',
                'quantity_green_grams' => '1500',
                'quantity_white_grams' => '1600',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_green_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_zero_in_both_varieties_is_rejected(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Empty',
                'email' => 'empty@example.com',
                'quantity_green_grams' => '0',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_green_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_below_500g_rejects_delivery_with_field_error(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Petra Klein',
                'email' => 'petra@example.com',
                'phone' => '+49 30 11111111',
                'quantity_green_grams' => '300',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'delivery',
                'delivery_street' => 'Kleine Str. 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('fulfillment_mode');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_at_500g_delivery_is_allowed(): void
    {
        // The threshold is inclusive: 500 g exactly should pass.
        $this->post(route('public.spargel.store'), [
            'name' => 'Carla Halbkilo',
            'email' => 'carla@example.com',
            'phone' => '+49 30 22222222',
            'quantity_green_grams' => '0',
            'quantity_white_grams' => '500',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Hauptstr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(1, SpargelOrder::query()->count());
    }

    public function test_phone_is_required_when_fulfillment_is_delivery(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Dora',
                'email' => 'dora@example.com',
                // phone intentionally omitted
                'quantity_green_grams' => '1000',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'delivery',
                'delivery_street' => 'Strasse 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_phone_stays_optional_when_fulfillment_is_pickup(): void
    {
        $this->post(route('public.spargel.store'), [
            'name' => 'Erik',
            'email' => 'erik@example.com',
            // no phone
            'quantity_green_grams' => '500',
            'quantity_white_grams' => '0',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(1, SpargelOrder::query()->count());
    }

    public function test_delivery_address_is_required_when_mode_is_delivery(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Frieda',
                'email' => 'frieda@example.com',
                'phone' => '+49 30 33333333',
                'quantity_green_grams' => '0',
                'quantity_white_grams' => '1500',
                'fulfillment_mode' => 'delivery',
                // address fields intentionally omitted
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors(['delivery_street', 'delivery_postal_code', 'delivery_city']);
    }

    public function test_consent_checkbox_is_required(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Greta',
                'email' => 'greta@example.com',
                'quantity_green_grams' => '500',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                // consent missing
            ])
            ->assertSessionHasErrors('consent');
    }

    public function test_pay_view_initiates_checkout_with_total_amount_and_renders_widget(): void
    {
        // 1500 g green * 21.50 = 32.25; +2.90 delivery = 35.15
        $order = SpargelOrder::factory()->create([
            'quantity_green_grams' => 1500,
            'quantity_white_grams' => 0,
            'fulfillment_mode' => 'delivery',
            'delivery_fee_eur' => 2.90,
            'total_amount_eur' => 35.15,
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createOneTimeCheckout')
            ->once()
            ->withArgs(function (
                float $amount,
                string $reference,
                string $description,
                ?string $customerId,
                array $extras
            ) use ($order) {
                return $amount === 35.15
                    && str_contains($reference, 'spargel-'.$order->id.'-')
                    && $description === 'Erdgut Spargel-Bestellung'
                    && $customerId === null
                    && isset($extras['redirect_url'])
                    && str_contains($extras['redirect_url'], '/spargel/'.$order->id.'/return');
            })
            ->andReturn((object) ['id' => 'co_test_spargel_abc']);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.pay', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee('co_test_spargel_abc')
            ->assertSee(__('spargel.pay_heading'));

        $order->refresh();
        $this->assertSame('co_test_spargel_abc', $order->sumup_checkout_id);
    }

    public function test_pay_view_redirects_to_return_when_order_already_paid(): void
    {
        $order = SpargelOrder::factory()->paid()->create();

        $this->get(route('public.spargel.pay', ['spargelOrder' => $order->id]))
            ->assertRedirect(route('public.spargel.return', ['spargelOrder' => $order->id]));
    }

    public function test_return_marks_order_paid_when_sumup_confirms(): void
    {
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_pending_xyz',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_pending_xyz')
            ->andReturn((object) [
                'status' => 'PAID',
                'transactions' => [(object) ['id' => 'pay_test_xyz']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_paid_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pay_test_xyz', $order->sumup_payment_id);
    }

    public function test_return_marks_order_paid_when_transaction_succeeded_but_checkout_status_lags(): void
    {
        // Regression: SumUp redirected the customer back while the checkout
        // `status` still read PENDING, even though the payment had already
        // settled (a SUCCESSFUL transaction is present). The order must be
        // marked paid instead of leaving the customer stuck on "verifying".
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_lagging_status',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_lagging_status')
            ->andReturn((object) [
                'status' => 'PENDING',
                'transactions' => [(object) ['id' => 'pay_lagging_xyz', 'status' => 'SUCCESSFUL']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_paid_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pay_lagging_xyz', $order->sumup_payment_id);
    }

    public function test_return_keeps_order_pending_when_sumup_says_pending(): void
    {
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_pending_xyz',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_pending_xyz')
            ->andReturn((object) ['status' => 'PENDING', 'transactions' => []]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_pending_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_return_marks_order_failed_when_checkout_status_is_failed(): void
    {
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_dead_xyz',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_dead_xyz')
            ->andReturn((object) ['status' => 'FAILED', 'transactions' => []]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_failed_heading'))
            ->assertDontSee(__('spargel.return_pending_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Failed, $order->status);
    }

    public function test_return_marks_order_failed_when_latest_transaction_is_failed(): void
    {
        // The 3DS-rejection case: the checkout itself stays PENDING but
        // the customer's transaction came back FAILED. We treat that as
        // "this attempt failed" so the customer sees the right UI.
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_3ds_dead',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_3ds_dead')
            ->andReturn((object) [
                'status' => 'PENDING',
                'transactions' => [(object) ['status' => 'FAILED', 'id' => 'tx_dead']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_failed_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Failed, $order->status);
    }

    public function test_pay_for_failed_order_resets_to_pending_and_creates_fresh_checkout(): void
    {
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_old_dead',
            'status' => SpargelOrderStatus::Failed,
            'payment_method' => 'online',
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createOneTimeCheckout')
            ->once()
            ->andReturn((object) ['id' => 'co_fresh_retry']);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.pay', ['spargelOrder' => $order->id]))
            ->assertOk();

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);
        $this->assertSame('co_fresh_retry', $order->sumup_checkout_id);
    }

    public function test_quote_handles_each_variety_alone_and_combined(): void
    {
        $service = app(SpargelOrderService::class);

        // Green only, pickup, no delivery fee.
        $green = $service->quote(300, 0, 'pickup');
        $this->assertSame(300, $green['quantity_green_grams']);
        $this->assertSame(0, $green['quantity_white_grams']);
        $this->assertSame(300, $green['total_grams']);
        $this->assertSame(21.50, $green['unit_price_eur']);
        $this->assertSame(6.45, $green['line_total_eur']); // 300 * 21.50 / 1000
        $this->assertSame(0.0, $green['delivery_fee_eur']);
        $this->assertSame(6.45, $green['total_amount_eur']);

        // White only, delivery, fee added.
        $white = $service->quote(0, 2000, 'delivery');
        $this->assertSame(0, $white['quantity_green_grams']);
        $this->assertSame(2000, $white['quantity_white_grams']);
        $this->assertSame(2000, $white['total_grams']);
        $this->assertSame(43.0, $white['line_total_eur']);
        $this->assertSame(2.90, $white['delivery_fee_eur']);
        $this->assertSame(45.90, $white['total_amount_eur']);

        // Mixed both, pickup. Total = (500+500)/1000 * 21.50 = 21.50
        $mixed = $service->quote(500, 500, 'pickup');
        $this->assertSame(1000, $mixed['total_grams']);
        $this->assertSame(21.50, $mixed['line_total_eur']);
        $this->assertSame(21.50, $mixed['total_amount_eur']);
    }

    public function test_privacy_page_is_public_and_lists_data_categories(): void
    {
        $this->get(route('public.privacy'))
            ->assertOk()
            ->assertSee(__('privacy.heading'))
            ->assertSee(__('privacy.section_data_heading'))
            ->assertSee(__('privacy.section_rights_heading'));
    }

    public function test_pay_in_person_with_pickup_skips_sumup_and_lands_on_return_page(): void
    {
        Mail::fake();

        $response = $this->post(route('public.spargel.store'), [
            'name' => 'Petra Persönlich',
            'email' => 'petra@example.com',
            'quantity_green_grams' => '500',
            'quantity_white_grams' => '0',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'in_person',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('in_person', $order->payment_method);
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);

        // Skip the SumUp pay step entirely.
        $response->assertRedirect(route('public.spargel.return', ['spargelOrder' => $order->id]));

        // Confirmation email is queued — only moment we tell the customer
        // we got their pre-order.
        Mail::assertQueued(SpargelOrderConfirmationMail::class, function ($mail) use ($order) {
            return $mail->order->is($order) && $mail->hasTo('petra@example.com');
        });
    }

    public function test_sumup_paid_path_queues_confirmation_email_via_return_url(): void
    {
        Mail::fake();

        $order = SpargelOrder::factory()->create([
            'email' => 'sumup-paid@example.com',
            'sumup_checkout_id' => 'co_paid_xyz',
            'payment_method' => 'online',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_paid_xyz')
            ->andReturn((object) [
                'status' => 'PAID',
                'transactions' => [(object) ['id' => 'pay_xyz']],
            ]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk();

        Mail::assertQueued(SpargelOrderConfirmationMail::class, function ($mail) use ($order) {
            return $mail->order->is($order->fresh()) && $mail->hasTo('sumup-paid@example.com');
        });
    }

    public function test_sumup_pending_path_does_not_queue_email(): void
    {
        Mail::fake();

        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_still_pending',
            'payment_method' => 'online',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_still_pending')
            ->andReturn((object) ['status' => 'PENDING', 'transactions' => []]);
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_pay_in_person_with_delivery_is_rejected(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Otto',
                'email' => 'otto@example.com',
                'phone' => '+49 30 12345678',
                'quantity_green_grams' => '0',
                'quantity_white_grams' => '1000',
                'fulfillment_mode' => 'delivery',
                'payment_method' => 'in_person',
                'delivery_street' => 'Musterstr. 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_pay_in_person_return_page_shows_reservation_message_not_payment_failed(): void
    {
        $order = SpargelOrder::factory()->create([
            'payment_method' => 'in_person',
            'fulfillment_mode' => 'pickup',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_pay_in_person_heading'))
            ->assertDontSee(__('spargel.return_pending_heading'));
    }

    public function test_pay_view_redirects_in_person_orders_to_return(): void
    {
        $order = SpargelOrder::factory()->create([
            'payment_method' => 'in_person',
            'fulfillment_mode' => 'pickup',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        // Even if a customer tries to access the SumUp pay URL directly,
        // we bounce them back — no SumUp checkout for in-person orders.
        $this->get(route('public.spargel.pay', ['spargelOrder' => $order->id]))
            ->assertRedirect(route('public.spargel.return', ['spargelOrder' => $order->id]));
    }

    public function test_pickup_order_persists_chosen_pickup_location(): void
    {
        $this->post(route('public.spargel.store'), [
            'name' => 'Lina Pickup',
            'email' => 'lina@example.com',
            'quantity_green_grams' => '500',
            'quantity_white_grams' => '0',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'gotenstrasse',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertSame('gotenstrasse', $order->pickup_location);
    }

    public function test_pickup_order_without_pickup_location_is_rejected(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Lazy',
                'email' => 'lazy@example.com',
                'quantity_green_grams' => '500',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                // pickup_location intentionally omitted
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('pickup_location');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_pickup_order_with_unknown_pickup_location_is_rejected(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Spoofy',
                'email' => 'spoofy@example.com',
                'quantity_green_grams' => '500',
                'quantity_white_grams' => '0',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'somewhere_else',
                'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
                'consent' => '1',
            ])
            ->assertSessionHasErrors('pickup_location');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_delivery_order_drops_any_pickup_location_value(): void
    {
        // Even if a delivery order POST includes a pickup_location, we
        // strip it on persist — the field is meaningless for delivery
        // and we don't want stale data on the row.
        $this->post(route('public.spargel.store'), [
            'name' => 'Liefer',
            'email' => 'liefer@example.com',
            'phone' => '+49 30 12345678',
            'quantity_green_grams' => '0',
            'quantity_white_grams' => '1000',
            'fulfillment_mode' => 'delivery',
            'pickup_location' => 'karl_kunger',
            'delivery_street' => 'Musterstr 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'expected_delivery_date' => self::DEFAULT_DELIVERY_MONDAY,
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNull($order->pickup_location);
    }

    public function test_return_page_shows_pickup_host_name_and_address(): void
    {
        $order = SpargelOrder::factory()->paid()->create([
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
        ]);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee('Arthur Hamcha')
            ->assertSee('Karl-Kunger-Straße 9')
            ->assertSee('12435 Berlin');
    }

    public function test_pay_reuses_existing_checkout_on_reload(): void
    {
        // Regression: hitting /pay a second time used to create a new SumUp
        // checkout and overwrite the DB, orphaning the checkout the widget
        // already loaded. Now /pay reuses the stored checkout ID.
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_already_exists',
            'status' => SpargelOrderStatus::PendingPayment,
            'payment_method' => 'online',
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldNotReceive('createOneTimeCheckout');
        $this->app->instance('sumup.spargel', $sumUp);

        $this->get(route('public.spargel.pay', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee('co_already_exists');

        $this->assertSame('co_already_exists', $order->fresh()->sumup_checkout_id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow(null);
        parent::tearDown();
    }
}
