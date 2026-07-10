<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The operations sections of the admin dashboard. */
class AdminDashboardOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_ops_sections_charts_and_keeps_trial_pipeline(): void
    {
        Producer::factory()->create();
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $order = ShopOrder::factory()->paid()->create([
            'expected_delivery_date' => Carbon::yesterday()->toDateString(),
            'total_amount_eur' => 107.0,
            'delivery_fee_eur' => 0, 'delivery_fee_vat_amount_eur' => 0, 'discount_eur' => 0,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 5, 'unit' => 'piece',
            'line_total_eur' => 107.0, 'vat_amount_eur' => 7.0,
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('app.admin.dashboard'))
            ->assertOk()
            // New operations sections.
            ->assertSee('data-testid="admin-ops-kpis"', false)
            ->assertSee('data-testid="admin-ops-revenue-chart"', false)
            ->assertSee('data-testid="admin-ops-top-products"', false)
            ->assertSee('id="admin-ops-weekly-data"', false)
            // The trial pipeline survives.
            ->assertSee('data-testid="admin-trial-pipeline"', false)
            // The old stat grid is gone.
            ->assertDontSee('data-testid="admin-metric-subs-active"', false);

        // The chart payload carries the seeded revenue (100 € net) and the product.
        $html = $response->getContent();
        $this->assertStringContainsString('"b2cNet":100', $html);
        $this->assertStringContainsString($product->name, $html);
    }
}
