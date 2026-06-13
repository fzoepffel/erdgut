<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Exports\ShopOrderDeliveriesSheet;
use App\Exports\ShopOrderFarmTotalsSheet;
use App\Exports\ShopOrdersExport;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Coverage for the two-sheet shop-orders export. Asserts auth gating, the
 * matching filter behavior, per-line vs. per-producer aggregation, and that
 * canonical quantities are converted to the product's display unit (g → kg,
 * ml → L) so Excel totals read the way the catalog does.
 */
class AdminShopOrderExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.admin.shop-orders.export'))->assertRedirect();
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.shop-orders.export'))
            ->assertForbidden();
    }

    public function test_admin_can_download_filename(): void
    {
        Excel::fake();
        $admin = User::factory()->admin()->create();
        ShopOrder::factory()->paid()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.export'))
            ->assertOk();

        Excel::assertDownloaded('erdgut-bestellungen-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_export_respects_status_and_date_range_filter(): void
    {
        Excel::fake();
        $admin = User::factory()->admin()->create();

        $inRange = ShopOrder::factory()->paid()->create([
            'expected_delivery_date' => '2026-06-10',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $inRange->id]);

        $outside = ShopOrder::factory()->paid()->create([
            'expected_delivery_date' => '2026-06-25',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $outside->id]);

        $abandoned = ShopOrder::factory()->create([
            'status' => ShopOrderStatus::PendingPayment,
            'payment_method' => 'online',
            'expected_delivery_date' => '2026-06-11',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $abandoned->id]);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.export', [
                'delivery_from' => '2026-06-10',
                'delivery_to' => '2026-06-12',
            ]))
            ->assertOk();

        $filename = 'erdgut-bestellungen-'.now()->format('Y-m-d').'.xlsx';
        Excel::assertDownloaded($filename, function (ShopOrdersExport $export) use ($inRange, $outside, $abandoned): bool {
            [$deliveries] = $export->sheets();
            /** @var ShopOrderDeliveriesSheet $deliveries */
            $ids = collect($deliveries->array())->pluck(0)->all();

            return in_array($inRange->id, $ids, true)
                && ! in_array($outside->id, $ids, true)
                && ! in_array($abandoned->id, $ids, true);
        });
    }

    public function test_deliveries_sheet_emits_one_row_per_line_with_display_unit(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->paid()->create([
            'name' => 'Anna Kunde',
            'customer_type' => CustomerType::Private,
            'expected_delivery_date' => '2026-06-10',
        ]);
        // 1500 g canonical → 1.5 kg display.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1500,
            'unit' => 'g',
            'line_total_eur' => 9.0,
        ]);

        $sheet = new ShopOrderDeliveriesSheet(
            Collection::make([$order->fresh(['items.product.producer.translations'])])
        );
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame($order->id, $row[0]);
        $this->assertSame('2026-06-10', $row[1]);
        $this->assertSame('Anna Kunde', $row[3]);
        $this->assertSame(__('shop.export.type_private'), $row[4]);
        $this->assertSame($product->name, $row[9]);
        $this->assertSame($producer->name, $row[10]);
        $this->assertSame(1.5, $row[11]);
        $this->assertSame('kg', $row[12]);
        $this->assertSame(9.0, $row[13]);
    }

    public function test_farm_totals_sheet_aggregates_per_producer_and_product(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farmA->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
        ]);
        $cucumber = Product::factory()->create([
            'producer_id' => $farmB->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
        ]);

        $orderOne = ShopOrder::factory()->paid()->create();
        ShopOrderItem::factory()->create([
            'shop_order_id' => $orderOne->id,
            'product_id' => $tomato->id,
            'quantity' => 2000, // 2 kg
            'unit' => 'g',
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $orderOne->id,
            'product_id' => $cucumber->id,
            'quantity' => 3,
            'unit' => 'piece',
        ]);

        $orderTwo = ShopOrder::factory()->paid()->create();
        ShopOrderItem::factory()->create([
            'shop_order_id' => $orderTwo->id,
            'product_id' => $tomato->id,
            'quantity' => 500, // 0.5 kg — same display unit, accumulates
            'unit' => 'g',
        ]);

        $sheet = new ShopOrderFarmTotalsSheet(Collection::make([
            $orderOne->fresh(['items.product.producer.translations']),
            $orderTwo->fresh(['items.product.producer.translations']),
        ]));
        $rows = collect($sheet->array());

        $tomatoRow = $rows->firstWhere(1, $tomato->name);
        $cucumberRow = $rows->firstWhere(1, $cucumber->name);
        $this->assertNotNull($tomatoRow);
        $this->assertNotNull($cucumberRow);
        $this->assertSame($farmA->name, $tomatoRow[0]);
        $this->assertSame(2.5, $tomatoRow[2]);
        $this->assertSame('kg', $tomatoRow[3]);
        $this->assertSame(2, $tomatoRow[4]); // 2 orders contributed
        $this->assertSame($farmB->name, $cucumberRow[0]);
        $this->assertSame(3.0, $cucumberRow[2]);
        $this->assertSame(1, $cucumberRow[4]);
    }

    public function test_index_export_link_passes_through_current_filter(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.index', [
                'status' => ShopOrderStatus::Paid->value,
                'delivery_from' => '2026-06-10',
                'delivery_to' => '2026-06-12',
            ]))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-export-link"', false);

        // Blade escapes the ampersands; assertSee the path + each filter
        // value rather than the literal URL to stay HTML-encoding agnostic.
        $response->assertSee('href="'.route('app.admin.shop-orders.index').'/export', false);
        $response->assertSee('status='.ShopOrderStatus::Paid->value, false);
        $response->assertSee('delivery_from=2026-06-10', false);
        $response->assertSee('delivery_to=2026-06-12', false);
    }
}
