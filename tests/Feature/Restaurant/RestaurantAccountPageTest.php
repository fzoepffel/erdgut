<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Mail\RestaurantOrderConfirmedMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantProfile;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** B2B self-service: Konto page (profile + password) and the invoices list. */
class RestaurantAccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_can_update_profile_and_address(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        $this->actingAs($profile->user)
            ->put(route('app.restaurant.account.update'), [
                'business_name' => 'Trattoria Vera',
                'contact_name' => 'Vera Rossi',
                'phone' => '+49 30 1234567',
                'billing_street' => 'Neue Str. 9',
                'billing_postal_code' => '10245',
                'billing_city' => 'Berlin',
            ])
            ->assertRedirect(route('app.restaurant.account.edit'));

        $profile->refresh();
        $this->assertSame('Trattoria Vera', $profile->business_name);
        $this->assertSame('Neue Str. 9', $profile->billing_street);
        $this->assertSame('+49 30 1234567', $profile->user->refresh()->phone);

        // The new address prefills the shop checkout.
        Producer::factory()->create();
        $p = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 2, 'vat_rate_percent' => 7, 'is_available' => true, 'is_published' => true]);
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => Carbon::tomorrow()->toDateString(), 'quantity' => 10, 'freshness_window_days' => 3]);
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.catalog'))
            ->assertOk()
            ->assertSee('Neue Str. 9');
    }

    public function test_password_change_requires_current_password(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['password' => Hash::make('old-secret-1')])->save();

        // Wrong current password → rejected.
        $this->actingAs($profile->user)
            ->put(route('app.restaurant.account.password'), [
                'current_password' => 'wrong',
                'password' => 'new-secret-99',
                'password_confirmation' => 'new-secret-99',
            ])
            ->assertSessionHasErrors('current_password');

        // Correct current password → changed.
        $this->actingAs($profile->user)
            ->put(route('app.restaurant.account.password'), [
                'current_password' => 'old-secret-1',
                'password' => 'new-secret-99',
                'password_confirmation' => 'new-secret-99',
            ])
            ->assertRedirect(route('app.restaurant.account.edit'));
        $this->assertTrue(Hash::check('new-secret-99', $profile->user->refresh()->password));
    }

    public function test_invoices_page_lists_own_invoices_only(): void
    {
        Producer::factory()->create();
        $p = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 2, 'vat_rate_percent' => 7, 'is_available' => true, 'is_published' => true]);
        ProductStockInflow::create(['product_id' => $p->id, 'received_on' => Carbon::tomorrow()->toDateString(), 'quantity' => 100, 'freshness_window_days' => 3]);

        $mine = RestaurantProfile::factory()->verified()->create();
        $other = RestaurantProfile::factory()->verified()->create();
        $svc = app(RestaurantOrderService::class);
        $invoiceSvc = app(RestaurantInvoiceService::class);

        $payload = fn () => [
            'items' => [['product_id' => $p->id, 'variant_id' => null, 'quantity' => 3]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ];
        $myOrder = $svc->submitOrder($mine->user, $payload());
        $otherOrder = $svc->submitOrder($other->user, $payload());
        $invoiceSvc->issueInvoice($myOrder);
        $invoiceSvc->issueInvoice($otherOrder);

        $this->actingAs($mine->user)
            ->get(route('app.restaurant.invoices.index'))
            ->assertOk()
            ->assertSee('data-testid="restaurant-invoice-row-'.$myOrder->id.'"', false)
            ->assertSee($myOrder->refresh()->invoice_number)
            ->assertDontSee('data-testid="restaurant-invoice-row-'.$otherOrder->id.'"', false);
    }

    public function test_settle_mail_itemizes_accepted_and_declined_lines(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['locale' => 'de'])->save();
        $request = app(RestaurantOrderService::class)->submitRequest($profile->user, [
            'items' => [
                ['name' => 'Wildkräuter', 'unit' => 'kg', 'quantity' => 2],
                ['name' => 'Erbsentriebe', 'unit' => 'g', 'quantity' => 500],
            ],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        $svc = app(RestaurantOrderService::class);
        [$a, $b] = $request->items()->orderBy('id')->get();
        $svc->acceptLine($a);
        $svc->declineLine($b);

        app()->setLocale('de');
        $html = (new RestaurantOrderConfirmedMail($request->refresh(), null))->render();
        $this->assertStringContainsString('Wildkräuter', $html);
        $this->assertStringContainsString(__('restaurant.line_accepted'), $html);
        $this->assertStringContainsString('Erbsentriebe', $html);
        $this->assertStringContainsString('line-through', $html);
        $this->assertStringContainsString(__('restaurant.line_declined'), $html);
    }
}
