<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The Anfrage-Sortiment switchboard + time-bound visibility overrides. */
class AdminRequestAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seasonalProduct(array $attrs = []): Product
    {
        Producer::factory()->create();

        return Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 2.0,
            'vat_rate_percent' => 7,
            'is_available' => true,
            'is_published' => true,
            'is_seasonal' => true, // no window → in season year-round
        ], $attrs));
    }

    public function test_page_shows_state_and_toggle_updates_with_expiry(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 7, 8));
        try {
            $product = $this->seasonalProduct();
            $admin = User::factory()->admin()->create();

            // In season → green.
            $this->actingAs($admin)
                ->get(route('app.admin.request-availability.index'))
                ->assertOk()
                ->assertSee('data-testid="request-availability-state-'.$product->id.'"', false)
                ->assertSee('bg-emerald-500');

            // Turn OFF until 2026-07-20.
            $this->actingAs($admin)
                ->post(route('app.admin.request-availability.update', $product), [
                    'action' => 'off', 'until' => '2026-07-20',
                ])
                ->assertRedirect(route('app.admin.request-availability.index'));
            $product->refresh();
            $this->assertSame('never', $product->request_visibility);
            $this->assertFalse($product->isRequestableOn(Carbon::parse('2026-07-15')));
            // After the expiry the season logic takes over again.
            $this->assertTrue($product->isRequestableOn(Carbon::parse('2026-07-25')));

            // Back to auto clears everything.
            $this->actingAs($admin)
                ->post(route('app.admin.request-availability.update', $product), ['action' => 'auto'])
                ->assertRedirect();
            $product->refresh();
            $this->assertSame('auto', $product->request_visibility);
            $this->assertNull($product->request_visibility_until);

            // Non-admin blocked.
            $this->actingAs(User::factory()->create())
                ->get(route('app.admin.request-availability.index'))
                ->assertForbidden();
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_temporary_feature_of_an_off_season_product_reaches_the_request_area(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 7, 8));
        try {
            // Out of season (window in November).
            $product = $this->seasonalProduct([
                'season_start_month' => 11, 'season_start_day' => 1,
                'season_end_month' => 11, 'season_end_day' => 30,
            ]);
            $admin = User::factory()->admin()->create();
            $this->assertFalse($product->isRequestableOn());

            // Feature it until 2026-07-20.
            $this->actingAs($admin)
                ->post(route('app.admin.request-availability.update', $product), [
                    'action' => 'on', 'until' => '2026-07-20',
                ])->assertRedirect();
            $product->refresh();
            $this->assertTrue($product->isRequestableOn(Carbon::parse('2026-07-10')));
            $this->assertFalse($product->isRequestableOn(Carbon::parse('2026-07-25')));

            // And restaurants actually see it in the Anfragen area now.
            $stocked = $this->seasonalProduct(['is_seasonal' => false]);
            ProductStockInflow::create([
                'product_id' => $stocked->id,
                'received_on' => Carbon::tomorrow()->toDateString(),
                'quantity' => 10, 'freshness_window_days' => 3,
            ]);
            $profile = RestaurantProfile::factory()->verified()->create();
            $this->actingAs($profile->user)
                ->get(route('app.restaurant.requests.index'))
                ->assertOk()
                ->assertSee('data-product-id="'.$product->id.'"', false);
        } finally {
            Carbon::setTestNow(null);
        }
    }
}
