<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Coupon;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Admin CRUD for shop coupons + the public live-preview endpoint.
 *
 * Guards the admin gate, the percent-<100 / fixed-needs-minimum safety
 * rules, and the storefront preview that re-prices the cart server-side
 * (coarse error messages, anti-enumeration).
 */
class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function validPercentPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10',
            'label' => 'Ten percent',
            'type' => 'percent',
            'value' => '10',
            'per_email_limit' => '1',
            'starts_at' => '2026-06-01T00:00',
            'expires_at' => '2026-12-31T23:59',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_guest_cannot_reach_coupons_index(): void
    {
        $this->get(route('app.admin.coupons.index'))->assertRedirect();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('app.admin.coupons.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_percent_coupon(): void
    {
        $this->actingAs($this->admin())
            ->post(route('app.admin.coupons.store'), $this->validPercentPayload())
            ->assertRedirect();

        $coupon = Coupon::query()->where('code', 'SAVE10')->first();
        $this->assertNotNull($coupon);
        $this->assertSame('percent', $coupon->type->value);
        $this->assertSame('10.00', (string) $coupon->value);
    }

    public function test_code_is_persisted_uppercased(): void
    {
        $this->actingAs($this->admin())
            ->post(route('app.admin.coupons.store'), $this->validPercentPayload(['code' => 'lower10']))
            ->assertRedirect();

        $this->assertDatabaseHas('coupons', ['code' => 'LOWER10']);
    }

    public function test_percent_value_above_max_is_rejected(): void
    {
        // config('coupon.max_percent') defaults to 90 — 100% off is forbidden.
        $this->actingAs($this->admin())
            ->from(route('app.admin.coupons.create'))
            ->post(route('app.admin.coupons.store'), $this->validPercentPayload(['value' => '100']))
            ->assertSessionHasErrors('value');

        $this->assertSame(0, Coupon::query()->count());
    }

    public function test_fixed_coupon_requires_minimum_order(): void
    {
        $this->actingAs($this->admin())
            ->from(route('app.admin.coupons.create'))
            ->post(route('app.admin.coupons.store'), [
                'code' => 'FIXED20',
                'type' => 'fixed',
                'value' => '20',
                'per_email_limit' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('min_order_eur');

        $this->assertSame(0, Coupon::query()->count());
    }

    public function test_fixed_coupon_minimum_must_be_at_least_its_value(): void
    {
        $this->actingAs($this->admin())
            ->from(route('app.admin.coupons.create'))
            ->post(route('app.admin.coupons.store'), [
                'code' => 'FIXED20',
                'type' => 'fixed',
                'value' => '20',
                'min_order_eur' => '10',
                'per_email_limit' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('min_order_eur');
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Coupon::factory()->create(['code' => 'TAKEN']);

        $this->actingAs($this->admin())
            ->from(route('app.admin.coupons.create'))
            ->post(route('app.admin.coupons.store'), $this->validPercentPayload(['code' => 'TAKEN']))
            ->assertSessionHasErrors('code');
    }

    public function test_validity_window_is_required(): void
    {
        // No coupon may be usable forever — both dates must be supplied.
        $this->actingAs($this->admin())
            ->from(route('app.admin.coupons.create'))
            ->post(route('app.admin.coupons.store'), $this->validPercentPayload([
                'starts_at' => '',
                'expires_at' => '',
            ]))
            ->assertSessionHasErrors(['starts_at', 'expires_at']);

        $this->assertSame(0, Coupon::query()->count());
    }

    public function test_expiry_must_not_predate_start(): void
    {
        $this->actingAs($this->admin())
            ->from(route('app.admin.coupons.create'))
            ->post(route('app.admin.coupons.store'), $this->validPercentPayload([
                'starts_at' => '2026-12-31T00:00',
                'expires_at' => '2026-06-01T00:00',
            ]))
            ->assertSessionHasErrors('expires_at');
    }

    public function test_admin_can_update_coupon(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'EDIT']);

        $this->actingAs($this->admin())
            ->put(route('app.admin.coupons.update', $coupon), $this->validPercentPayload([
                'code' => 'EDIT',
                'value' => '25',
            ]))
            ->assertRedirect();

        $this->assertSame('25.00', (string) $coupon->fresh()->value);
    }

    public function test_admin_can_toggle_active_state(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.coupons.toggle', $coupon))
            ->assertRedirect();

        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_admin_can_delete_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('app.admin.coupons.destroy', $coupon))
            ->assertRedirect(route('app.admin.coupons.index'));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_show_renders_coupon_and_redemptions_section(): void
    {
        $coupon = Coupon::factory()->percent(10)->create(['code' => 'SHOWME']);

        $this->actingAs($this->admin())
            ->get(route('app.admin.coupons.show', $coupon))
            ->assertOk()
            ->assertSee('SHOWME');
    }

    // --- Public live-preview endpoint -------------------------------------

    private function seedProduct(string $date): Product
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.00,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $date,
            'quantity' => 20,
            'freshness_window_days' => 1,
        ]);

        return $product;
    }

    public function test_preview_applies_valid_coupon_and_returns_new_total(): void
    {
        Carbon::setTestNow('2026-05-30 10:00:00');
        $product = $this->seedProduct('2026-06-05');
        Coupon::factory()->percent(10)->create(['code' => 'PREVIEW10']);

        $this->postJson(route('public.shop.coupon.preview'), [
            'coupon_code' => 'PREVIEW10',
            'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup',
            'quantities' => [$product->id => 3], // 30.00 subtotal
        ])->assertOk()->assertJson([
            'applied' => true,
            'code' => 'PREVIEW10',
            'items_total_eur' => 30.00,
            'discount_eur' => 3.00,
            'total_amount_eur' => 27.00,
        ]);

        Carbon::setTestNow(null);
    }

    public function test_preview_returns_coarse_message_for_unknown_code(): void
    {
        Carbon::setTestNow('2026-05-30 10:00:00');
        $product = $this->seedProduct('2026-06-05');

        $response = $this->postJson(route('public.shop.coupon.preview'), [
            'coupon_code' => 'DOESNOTEXIST',
            'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup',
            'quantities' => [$product->id => 3],
        ])->assertOk();

        $response->assertJson(['applied' => false]);
        $this->assertSame(__('coupon.errors.invalid'), $response->json('message'));

        Carbon::setTestNow(null);
    }

    public function test_preview_reports_minimum_order_message(): void
    {
        Carbon::setTestNow('2026-05-30 10:00:00');
        $product = $this->seedProduct('2026-06-05');
        Coupon::factory()->fixed(15, minOrder: 50.00)->create(['code' => 'NEEDS50']);

        $response = $this->postJson(route('public.shop.coupon.preview'), [
            'coupon_code' => 'NEEDS50',
            'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup',
            'quantities' => [$product->id => 3], // 30.00 < 50
        ])->assertOk();

        $response->assertJson(['applied' => false]);
        $this->assertSame(
            __('coupon.errors.min_order', ['min' => '50,00']),
            $response->json('message'),
        );

        Carbon::setTestNow(null);
    }
}
