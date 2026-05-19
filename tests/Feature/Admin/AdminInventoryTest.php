<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.admin.inventory.index'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.inventory.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_inventory_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->assertSee(__('admin.inventory.title'));
    }

    public function test_admin_can_record_a_manual_inflow_and_redirect_back(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => 'mass',
            'price_display_unit' => 'kg',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.inventory.inflows.store'), [
                'product_id' => $product->id,
                'received_on' => '2026-05-25',
                'quantity' => 10, // 10 kg → 10000 g canonical
                'freshness_window_days' => 3,
                'notes' => 'Today harvest',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_stock_inflows', [
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 10000.0,
            'freshness_window_days' => 3,
        ]);
    }

    public function test_admin_can_delete_an_inflow(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $inflow = ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 100,
            'freshness_window_days' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('app.admin.inventory.inflows.destroy', $inflow))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_stock_inflows', ['id' => $inflow->id]);
    }

    public function test_legacy_weekly_catalog_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('app.admin.products.weekly.show'));
        $this->assertFalse(Route::has('app.admin.products.weekly.save'));
    }
}
