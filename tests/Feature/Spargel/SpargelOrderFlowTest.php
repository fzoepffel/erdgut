<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Mail\SpargelOrderConfirmationMail;
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
            ->assertSee(__('spargel.section_product'))
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

    /**
     * White-only orders use the looser Sunday cutoff: a Friday-night
     * white-only order should still ship the very next Monday rather
     * than the Monday a week later.
     */
    public function test_white_only_friday_order_ships_coming_monday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 22:00:00')); // Fri night

        try {
            $this->post(route('public.spargel.store'), [
                'name' => 'Wanda Weiss',
                'email' => 'wanda@example.com',
                'quantity_green_grams' => '0',
                'quantity_white_grams' => '500',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'consent' => '1',
            ]);

            $order = SpargelOrder::query()->latest('id')->first();
            $this->assertNotNull($order);
            // 2026-05-04 = the Monday after that Friday — *not* +1 week.
            $this->assertSame('2026-05-04', $order->expected_delivery_date->toDateString());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    /**
     * Counterpoint to the white-only test above: a Friday order with any
     * green in the cart still bumps to the Monday after next, because
     * green's strict Thursday cutoff has already passed.
     */
    public function test_mixed_friday_order_shifts_one_week_because_of_green(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 22:00:00')); // Fri night

        try {
            $this->post(route('public.spargel.store'), [
                'name' => 'Mira Mixed',
                'email' => 'mira@example.com',
                'quantity_green_grams' => '200',
                'quantity_white_grams' => '300',
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'consent' => '1',
            ]);

            $order = SpargelOrder::query()->latest('id')->first();
            $this->assertNotNull($order);
            // 2026-05-11 = the Monday a week after the Friday order.
            $this->assertSame('2026-05-11', $order->expected_delivery_date->toDateString());
        } finally {
            Carbon::setTestNow(null);
        }
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

    /**
     * Green-only / mixed cart cutoff:
     *
     *   Mon–Thu  → coming Monday            (Thu = cutoff)
     *   Fri–Sun  → following Monday         (past cutoff → +1 week)
     *
     * @dataProvider greenCutoffProvider
     */
    public function test_next_delivery_monday_uses_thursday_cutoff_for_green(string $orderDate, string $expectedDelivery): void
    {
        $service = app(SpargelOrderService::class);
        $monday = $service->nextDeliveryMonday(Carbon::parse($orderDate), greenGrams: 500, whiteGrams: 0);

        $this->assertSame($expectedDelivery, $monday->toDateString());
    }

    public static function greenCutoffProvider(): array
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

    /**
     * White-only cart cutoff: every day Mon–Sun ships the coming Monday
     * (Sunday is the cutoff). Even Sunday-night orders make it.
     *
     * @dataProvider whiteCutoffProvider
     */
    public function test_next_delivery_monday_uses_sunday_cutoff_for_white_only(string $orderDate, string $expectedDelivery): void
    {
        $service = app(SpargelOrderService::class);
        $monday = $service->nextDeliveryMonday(Carbon::parse($orderDate), greenGrams: 0, whiteGrams: 500);

        $this->assertSame($expectedDelivery, $monday->toDateString());
    }

    public static function whiteCutoffProvider(): array
    {
        return [
            'order Monday' => ['2026-04-27 10:00', '2026-05-04'],
            'order Tuesday' => ['2026-04-28 10:00', '2026-05-04'],
            'order Wednesday' => ['2026-04-29 10:00', '2026-05-04'],
            'order Thursday' => ['2026-04-30 23:00', '2026-05-04'],
            'order Friday' => ['2026-05-01 09:00', '2026-05-04'],
            'order Saturday' => ['2026-05-02 09:00', '2026-05-04'],
            'order Sunday' => ['2026-05-03 23:00', '2026-05-04'],
        ];
    }

    /**
     * Mixed-cart orders behave like green-only — the strictest cutoff in
     * the cart wins, so a Friday order with both varieties ships the
     * Monday after next.
     */
    public function test_next_delivery_monday_uses_strictest_cutoff_for_mixed_cart(): void
    {
        $service = app(SpargelOrderService::class);

        $monday = $service->nextDeliveryMonday(
            Carbon::parse('2026-05-01 09:00'), // Fri — past green cutoff
            greenGrams: 200,
            whiteGrams: 300,
        );

        $this->assertSame('2026-05-11', $monday->toDateString());
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

    public function test_form_renders_per_variety_quantity_dropdowns(): void
    {
        $html = $this->get(route('public.spargel.show'))
            ->assertOk()
            ->getContent();

        // Two <select> dropdowns — one per variety.
        $this->assertStringContainsString('name="quantity_green_grams"', $html);
        $this->assertStringContainsString('name="quantity_white_grams"', $html);
        // 50 g multiples are present in the option list.
        $this->assertStringContainsString('value="0"', $html);
        $this->assertStringContainsString('value="100"', $html);
        $this->assertStringContainsString('value="500"', $html);
        $this->assertStringContainsString('value="3000"', $html);
        // The collapsed single-quantity name is gone.
        $this->assertStringNotContainsString('name="quantity_grams"', $html);
        $this->assertStringNotContainsString('name="asparagus_type"', $html);
    }

    public function test_form_renders_both_submit_buttons_so_alpine_can_pick(): void
    {
        // Both buttons exist in the DOM with x-show toggling visibility —
        // online (Weiter zur Zahlung) and in-person (Bestellung bestätigen).
        $html = $this->get(route('public.spargel.show'))
            ->assertOk()
            ->assertSee(__('spargel.cta_continue_to_payment'))
            ->assertSee(__('spargel.cta_confirm_order'))
            ->assertSee('data-testid="spargel-submit-online"', false)
            ->assertSee('data-testid="spargel-submit-in-person"', false)
            ->getContent();

        // Confirmation modal is in the DOM (hidden behind x-cloak).
        $this->assertStringContainsString('data-testid="spargel-confirm-modal"', $html);
        $this->assertStringContainsString('data-testid="spargel-confirm-cancel"', $html);
        $this->assertStringContainsString('data-testid="spargel-confirm-submit"', $html);
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
        $this->app->instance(SumUpService::class, $sumUp);

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
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUp);

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
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNull($order->pickup_location);
    }

    public function test_form_renders_pickup_location_chooser_with_addresses_only(): void
    {
        $html = $this->get(route('public.spargel.show'))
            ->assertOk()
            ->getContent();

        // Both pickup-location radios are present.
        $this->assertStringContainsString('value="karl_kunger"', $html);
        $this->assertStringContainsString('value="gotenstrasse"', $html);
        // Addresses are visible on the form.
        $this->assertStringContainsString('Karl-Kunger-Straße 9', $html);
        $this->assertStringContainsString('Gotenstrasse 19', $html);
        // …but the host names are NOT shown on the order page.
        $this->assertStringNotContainsString('Arthur Hamcha', $html);
        $this->assertStringNotContainsString('Milan Ross', $html);
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
