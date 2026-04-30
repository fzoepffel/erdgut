<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use App\Services\SpargelOrderService;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Trial-shop end-to-end coverage for the standalone spargel sales channel.
 *
 * The flow is: form (GET /spargel) → submit (POST /spargel) → SumUp pay
 * (GET /spargel/{order}/pay) → SumUp redirect (GET /spargel/{order}/return).
 *
 * Tests assert at each hop that:
 * - the right status transitions happen on the SpargelOrder row,
 * - SumUpService is called with the right amount/reference/return-url,
 * - the public surface stays decoupled from User/Order/Subscription tables.
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

    public function test_coming_soon_renders_link_to_form(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-testid="coming-soon-spargel-cta"', false)
            ->assertSee(route('public.spargel.show'));
    }

    public function test_submitting_valid_pickup_creates_pending_order_and_redirects_to_pay(): void
    {
        $response = $this->post(route('public.spargel.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'phone' => '+49 30 12345678',
            'asparagus_type' => 'green',
            'quantity_kg' => '0.5',
            'fulfillment_mode' => 'pickup',
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('Anna Mustermann', $order->name);
        $this->assertSame('anna@example.com', $order->email);
        $this->assertSame('green', $order->asparagus_type);
        $this->assertSame('0.50', (string) $order->quantity_kg);
        $this->assertSame('pickup', $order->fulfillment_mode);
        $this->assertSame('0.00', (string) $order->delivery_fee_eur);
        // 0.5 kg * 20 €/kg = 10.00
        $this->assertSame('10.00', (string) $order->total_amount_eur);
        $this->assertSame(SpargelOrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->delivery_street);

        $response->assertRedirect(route('public.spargel.pay', ['spargelOrder' => $order->id]));
    }

    public function test_submitting_delivery_attaches_address_and_adds_fee(): void
    {
        $this->post(route('public.spargel.store'), [
            'name' => 'Bernd Liefert',
            'email' => 'bernd@example.com',
            'asparagus_type' => 'white',
            'quantity_kg' => '2.0',
            'fulfillment_mode' => 'delivery',
            'delivery_street' => 'Musterstraße 12',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
            'consent' => '1',
        ]);

        $order = SpargelOrder::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('delivery', $order->fulfillment_mode);
        $this->assertSame('1.99', (string) $order->delivery_fee_eur);
        // 2 kg * 20 + 1.99 = 41.99
        $this->assertSame('41.99', (string) $order->total_amount_eur);
        $this->assertSame('Musterstraße 12', $order->delivery_street);
        $this->assertSame('10115', $order->delivery_postal_code);
        $this->assertSame('Berlin', $order->delivery_city);
    }

    public function test_below_minimum_delivery_quantity_rejects_with_field_error(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Petra Klein',
                'email' => 'petra@example.com',
                'asparagus_type' => 'green',
                'quantity_kg' => '0.5',
                'fulfillment_mode' => 'delivery',
                'delivery_street' => 'Kleine Str. 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
                'consent' => '1',
            ])
            ->assertSessionHasErrors('fulfillment_mode');

        $this->assertSame(0, SpargelOrder::query()->count());
    }

    public function test_delivery_address_is_required_when_mode_is_delivery(): void
    {
        $this->from(route('public.spargel.show'))
            ->post(route('public.spargel.store'), [
                'name' => 'Carla',
                'email' => 'carla@example.com',
                'asparagus_type' => 'white',
                'quantity_kg' => '1.5',
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
                'name' => 'Dora',
                'email' => 'dora@example.com',
                'asparagus_type' => 'green',
                'quantity_kg' => '1.0',
                'fulfillment_mode' => 'pickup',
                // consent missing
            ])
            ->assertSessionHasErrors('consent');
    }

    public function test_pay_view_initiates_checkout_with_total_amount_and_renders_widget(): void
    {
        $order = SpargelOrder::factory()->create([
            'quantity_kg' => 1.5,
            'fulfillment_mode' => 'delivery',
            'delivery_fee_eur' => 1.99,
            'total_amount_eur' => 31.99,
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
                return $amount === 31.99
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

    public function test_quote_returns_correct_total_for_pickup_and_delivery(): void
    {
        $service = app(SpargelOrderService::class);

        $pickup = $service->quote(0.5, 'pickup');
        $this->assertSame(0.5, $pickup['quantity_kg']);
        $this->assertSame(20.0, $pickup['unit_price_eur']);
        $this->assertSame(10.0, $pickup['line_total_eur']);
        $this->assertSame(0.0, $pickup['delivery_fee_eur']);
        $this->assertSame(10.0, $pickup['total_amount_eur']);

        $delivery = $service->quote(2.0, 'delivery');
        $this->assertSame(40.0, $delivery['line_total_eur']);
        $this->assertSame(1.99, $delivery['delivery_fee_eur']);
        $this->assertSame(41.99, $delivery['total_amount_eur']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
