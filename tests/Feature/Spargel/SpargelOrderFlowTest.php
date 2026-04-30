<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use App\Services\SpargelOrderService;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Trial-shop end-to-end coverage for the standalone spargel sales channel.
 *
 * Flow: form (GET /spargel) → submit (POST /spargel) → SumUp pay
 * (GET /spargel/{order}/pay) → SumUp redirect (GET /spargel/{order}/return).
 *
 * Tests assert at each hop:
 * - the right status transitions happen on the SpargelOrder row,
 * - SumUpService is called with the right amount/reference/return-url,
 * - the public surface stays decoupled from User/Order/Subscription.
 */
class SpargelOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_form_renders_publicly_without_auth(): void
    {
        $this->get(route('public.spargel.show'))
            ->assertOk()
            ->assertSee(__('spargel.heading'))
            ->assertSee(__('spargel.section_contact'))
            ->assertSee(__('spargel.section_quantity'))
            ->assertSee('data-testid="spargel-form"', false);
    }

    public function test_form_surfaces_next_delivery_monday_banner(): void
    {
        // Lock 'now' to a Tuesday so we know the cutoff math.
        Carbon::setTestNow(Carbon::parse('2026-04-28 10:00:00')); // Tuesday

        try {
            $response = $this->get(route('public.spargel.show'))
                ->assertOk()
                ->assertSee(__('spargel.delivery_window_rule'));

            // Render the expected label in the same locale the response just
            // used — `app()->getLocale()` reflects whatever the locale
            // middleware resolved during the request, which may differ
            // from the locale of the surrounding test process.
            $expectedLabel = Carbon::parse('2026-05-04')
                ->locale(app()->getLocale())
                ->isoFormat('dddd, D. MMMM');

            $response->assertSee($expectedLabel);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_coming_soon_renders_link_to_form(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-testid="coming-soon-spargel-cta"', false)
            ->assertSee(route('public.spargel.show'));
    }

    public function test_submitting_valid_pickup_creates_pending_order_in_grams(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-28 10:00:00')); // Tue

        try {
            $response = $this->post(route('public.spargel.store'), [
                'name' => 'Anna Mustermann',
                'email' => 'anna@example.com',
                'phone' => '+49 30 12345678',
                'asparagus_type' => 'green',
                'quantity_grams' => '300',
                'fulfillment_mode' => 'pickup',
                'consent' => '1',
            ]);

            $order = SpargelOrder::query()->latest('id')->first();
            $this->assertNotNull($order);
            $this->assertSame('Anna Mustermann', $order->name);
            $this->assertSame('green', $order->asparagus_type);
            $this->assertSame(300, $order->quantity_grams);
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

    public function test_submitting_delivery_attaches_address_phone_and_fee(): void
    {
        $this->post(route('public.spargel.store'), [
            'name' => 'Bernd Liefert',
            'email' => 'bernd@example.com',
            'phone' => '+49 30 99887766',
            'asparagus_type' => 'white',
            'quantity_grams' => '2000',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Musterstraße 12',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('delivery', $order->fulfillment_mode);
        $this->assertSame(2000, $order->quantity_grams);
        $this->assertSame('2.90', (string) $order->delivery_fee_eur);
        // 2000 g * 21.50 + 2.90 = 45.90
        $this->assertSame('45.90', (string) $order->total_amount_eur);
        $this->assertSame('Musterstraße 12', $order->delivery_street);
        $this->assertSame('+49 30 99887766', $order->phone);
    }

    public function test_quantity_must_be_a_multiple_of_100_grams(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Quentin',
                'email' => 'quentin@example.com',
                'asparagus_type' => 'green',
                'quantity_grams' => '350', // not a multiple of 100
                'fulfillment_mode' => 'pickup',
                'consent' => '1',
            ])
            ->assertSessionHasErrors('quantity_grams');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_below_500g_rejects_delivery_with_field_error(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Petra Klein',
                'email' => 'petra@example.com',
                'phone' => '+49 30 11111111',
                'asparagus_type' => 'green',
                'quantity_grams' => '300',
                'fulfillment_mode' => 'delivery',
                'delivery_street' => 'Kleine Str. 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
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
            'asparagus_type' => 'white',
            'quantity_grams' => '500',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Hauptstr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
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
                'asparagus_type' => 'green',
                'quantity_grams' => '1000',
                'fulfillment_mode' => 'delivery',
                'delivery_street' => 'Strasse 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
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
            'asparagus_type' => 'green',
            'quantity_grams' => '500',
            'fulfillment_mode' => 'pickup',
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
                'asparagus_type' => 'white',
                'quantity_grams' => '1500',
                'fulfillment_mode' => 'delivery',
                // address fields intentionally omitted
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
                'asparagus_type' => 'green',
                'quantity_grams' => '500',
                'fulfillment_mode' => 'pickup',
                // consent missing
            ])
            ->assertSessionHasErrors('consent');
    }

    public function test_pay_view_initiates_checkout_with_total_amount_and_renders_widget(): void
    {
        // 1500 g * 21.50 = 32.25; +2.90 delivery = 35.15
        $order = SpargelOrder::factory()->create([
            'quantity_grams' => 1500,
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
        $this->app->instance(SumUpService::class, $sumUp);

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
        $this->app->instance(SumUpService::class, $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_paid_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pay_test_xyz', $order->sumup_payment_id);
    }

    public function test_return_keeps_order_pending_when_sumup_says_not_paid(): void
    {
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'co_pending_xyz',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->with('co_pending_xyz')
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUp);

        $this->get(route('public.spargel.return', ['spargelOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('spargel.return_pending_heading'));

        $order->refresh();
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_quote_returns_correct_total_in_grams(): void
    {
        $service = app(SpargelOrderService::class);

        $pickup = $service->quote(300, 'pickup');
        $this->assertSame(300, $pickup['quantity_grams']);
        $this->assertSame(21.50, $pickup['unit_price_eur']);
        // 300 g * 21.50 = 6.45
        $this->assertSame(6.45, $pickup['line_total_eur']);
        $this->assertSame(0.0, $pickup['delivery_fee_eur']);
        $this->assertSame(6.45, $pickup['total_amount_eur']);

        $delivery = $service->quote(2000, 'delivery');
        // 2000 g * 21.50 = 43.00
        $this->assertSame(43.0, $delivery['line_total_eur']);
        $this->assertSame(2.90, $delivery['delivery_fee_eur']);
        // 43 + 2.90 = 45.90
        $this->assertSame(45.90, $delivery['total_amount_eur']);
    }

    /**
     * Cutoff calendar:
     *
     *   Mon (iso 1) → next Mon            (this Monday, +7 days)
     *   Tue (iso 2) → next Mon            (+6)
     *   Wed (iso 3) → next Mon            (+5)
     *   Thu (iso 4) → next Mon            (+4)
     *   Fri (iso 5) → following Mon       (+10)
     *   Sat (iso 6) → following Mon       (+9)
     *   Sun (iso 7) → following Mon       (+8)
     *
     * @dataProvider deliveryMondayProvider
     */
    public function test_next_delivery_monday_respects_thursday_cutoff(string $orderDate, string $expectedDelivery): void
    {
        $service = app(SpargelOrderService::class);
        $monday = $service->nextDeliveryMonday(Carbon::parse($orderDate));

        $this->assertSame($expectedDelivery, $monday->toDateString());
    }

    public static function deliveryMondayProvider(): array
    {
        // Reference week: 2026-04-27 (Mon) → 2026-05-03 (Sun).
        return [
            'order Monday' => ['2026-04-27 10:00', '2026-05-04'],
            'order Tuesday' => ['2026-04-28 10:00', '2026-05-04'],
            'order Wednesday' => ['2026-04-29 10:00', '2026-05-04'],
            'order Thursday' => ['2026-04-30 23:00', '2026-05-04'],
            'order Friday' => ['2026-05-01 09:00', '2026-05-11'],
            'order Saturday' => ['2026-05-02 09:00', '2026-05-11'],
            'order Sunday' => ['2026-05-03 23:00', '2026-05-11'],
        ];
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
        $response = $this->post(route('public.spargel.store'), [
            'name' => 'Petra Persönlich',
            'email' => 'petra@example.com',
            'asparagus_type' => 'green',
            'quantity_grams' => '500',
            'fulfillment_mode' => 'pickup',
            'payment_method' => 'in_person',
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('in_person', $order->payment_method);
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);

        // Skip the SumUp pay step entirely.
        $response->assertRedirect(route('public.spargel.return', ['spargelOrder' => $order->id]));
    }

    public function test_pay_in_person_with_delivery_is_rejected(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Otto',
                'email' => 'otto@example.com',
                'phone' => '+49 30 12345678',
                'asparagus_type' => 'white',
                'quantity_grams' => '1000',
                'fulfillment_mode' => 'delivery',
                'payment_method' => 'in_person',
                'delivery_street' => 'Musterstr. 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
