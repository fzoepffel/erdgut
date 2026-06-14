<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Exports\ShopOrderCustomerSummarySheet;
use App\Exports\ShopOrderFarmTotalsSheet;
use App\Exports\ShopOrderPicklistSheet;
use App\Exports\ShopOrderPickupsSheet;
use App\Exports\ShopOrderRouteSheet;
use App\Exports\ShopOrdersExport;
use App\Exports\ShopOrderSummarySheet;
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
 * Coverage for the six-sheet shop-orders export. Asserts auth gating, that
 * the filter is reused for the file, and the shape of each individual sheet
 * (per-line, per-order, per-producer, route, pickups, KPI summary).
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
            $sheets = $export->sheets();
            $picklist = $sheets[2];
            self::assertInstanceOf(ShopOrderPicklistSheet::class, $picklist);
            $ids = collect($picklist->array())->pluck(0)->all();

            return in_array($inRange->id, $ids, true)
                && ! in_array($outside->id, $ids, true)
                && ! in_array($abandoned->id, $ids, true);
        });
    }

    public function test_workbook_exposes_six_sheets_in_canonical_order(): void
    {
        $export = new ShopOrdersExport(
            Collection::make(),
            ['status' => 'finalized', 'delivery_from' => '', 'delivery_to' => ''],
        );
        $sheets = $export->sheets();

        $this->assertInstanceOf(ShopOrderSummarySheet::class, $sheets[0]);
        $this->assertInstanceOf(ShopOrderCustomerSummarySheet::class, $sheets[1]);
        $this->assertInstanceOf(ShopOrderPicklistSheet::class, $sheets[2]);
        $this->assertInstanceOf(ShopOrderFarmTotalsSheet::class, $sheets[3]);
        $this->assertInstanceOf(ShopOrderRouteSheet::class, $sheets[4]);
        $this->assertInstanceOf(ShopOrderPickupsSheet::class, $sheets[5]);
    }

    public function test_picklist_sheet_emits_one_row_per_line_with_display_unit_and_vat(): void
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
            'delivery_window_from' => '14:00:00',
            'delivery_window_to' => '16:00:00',
        ]);
        // 1500 g canonical → 1.5 kg display. Snapshot price 0.009 €/g → 9 €/kg.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1500,
            'unit' => 'g',
            'unit_price_snapshot_eur' => 0.009,
            'line_total_eur' => 13.5,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 0.95,
        ]);

        $sheet = new ShopOrderPicklistSheet(
            Collection::make([$order->fresh(['items.product.producer.translations'])])
        );
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame($order->id, $row[0]);
        $this->assertSame('2026-06-10', $row[1]);
        $this->assertSame('14:00 – 16:00', $row[2]);
        $this->assertSame('Anna Kunde', $row[3]);
        $this->assertSame(__('shop.export.type_private'), $row[4]);
        $this->assertSame($product->name, $row[5]);
        $this->assertSame($producer->name, $row[6]);
        $this->assertSame(1.5, $row[7]);
        $this->assertSame('kg', $row[8]);
        $this->assertSame(9.0, $row[9]);
        $this->assertSame(13.5, $row[10]);
        $this->assertSame(7, $row[11]);
        $this->assertSame(0.95, $row[12]);
    }

    public function test_customer_summary_sheet_emits_one_row_per_order(): void
    {
        $orderA = ShopOrder::factory()->paid()->create([
            'name' => 'Order A',
            'fulfillment_mode' => 'delivery',
            'expected_delivery_date' => '2026-06-10',
            'delivery_street' => 'Karl-Marx-Allee 1',
            'delivery_postal_code' => '10178',
            'delivery_city' => 'Berlin',
            'delivery_notes' => 'Ring twice',
            'items_total_eur' => 20.0,
            'delivery_fee_eur' => 2.9,
            'discount_eur' => 0,
            'total_amount_eur' => 22.9,
        ]);
        ShopOrderItem::factory()->count(2)->create(['shop_order_id' => $orderA->id]);

        $orderB = ShopOrder::factory()->paid()->create([
            'name' => 'Order B',
            'fulfillment_mode' => 'pickup',
            'expected_delivery_date' => '2026-06-09',
            'pickup_location' => 'karl_kunger',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $orderB->id]);

        $sheet = new ShopOrderCustomerSummarySheet(Collection::make([
            $orderA->fresh(['items.product.producer.translations']),
            $orderB->fresh(['items.product.producer.translations']),
        ]));
        $rows = $sheet->array();

        // Sorted by date ascending → Order B (06-09) appears before Order A (06-10).
        $this->assertSame($orderB->id, $rows[0][0]);
        $this->assertSame($orderA->id, $rows[1][0]);
        $this->assertSame(2, $rows[1][13]); // item count
        $this->assertSame('Karl-Marx-Allee 1, 10178 Berlin', $rows[1][10]);
        $this->assertSame('Ring twice', $rows[1][12]);
    }

    public function test_farm_totals_sheet_aggregates_with_cost_and_margin(): void
    {
        $farm = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farm->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.006, // 6 €/kg canonical
            'buying_price_cents' => 300, // 3 €/kg buying
        ]);

        $orderOne = ShopOrder::factory()->paid()->create(['email' => 'a@example.com']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $orderOne->id,
            'product_id' => $tomato->id,
            'quantity' => 2000, // 2 kg
            'unit' => 'g',
            'line_total_eur' => 12.0,
        ]);

        $orderTwo = ShopOrder::factory()->paid()->create(['email' => 'b@example.com']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $orderTwo->id,
            'product_id' => $tomato->id,
            'quantity' => 500, // 0.5 kg
            'unit' => 'g',
            'line_total_eur' => 3.0,
        ]);

        $sheet = new ShopOrderFarmTotalsSheet(Collection::make([
            $orderOne->fresh(['items.product.producer.translations']),
            $orderTwo->fresh(['items.product.producer.translations']),
        ]));
        $rows = collect($sheet->array());

        $row = $rows->firstWhere(1, $tomato->name);
        $this->assertNotNull($row);
        $this->assertSame($farm->name, $row[0]);
        $this->assertSame(2.5, $row[2]); // qty kg
        $this->assertSame('kg', $row[3]);
        $this->assertSame(2, $row[4]); // 2 orders
        $this->assertSame(2, $row[5]); // 2 distinct customers
        $this->assertSame(3.0, $row[6]); // €/kg buying
        $this->assertSame(7.5, $row[7]); // 3 × 2.5
        $this->assertSame(15.0, $row[8]); // revenue 12 + 3
        $this->assertSame(7.5, $row[9]); // margin
    }

    public function test_route_sheet_includes_only_delivery_orders_with_payment_due(): void
    {
        $delivery = ShopOrder::factory()->create([
            'name' => 'Delivery Customer',
            'fulfillment_mode' => 'delivery',
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
            'expected_delivery_date' => '2026-06-10',
            'delivery_street' => 'Hauptstr. 42',
            'delivery_postal_code' => '10999',
            'delivery_city' => 'Berlin',
            'total_amount_eur' => 19.5,
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $delivery->id]);

        $pickup = ShopOrder::factory()->paid()->create([
            'name' => 'Pickup Customer',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'gotenstrasse',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $pickup->id]);

        $sheet = new ShopOrderRouteSheet(Collection::make([
            $delivery->fresh(['items.product.producer.translations']),
            $pickup->fresh(['items.product.producer.translations']),
        ]));
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $this->assertSame('10999', $rows[0][0]);
        $this->assertSame('Hauptstr. 42', $rows[0][2]);
        $this->assertSame($delivery->id, $rows[0][5]);
        $this->assertSame(19.5, $rows[0][9]); // total
        $this->assertSame(19.5, $rows[0][10]); // payment due — in-person + pending
    }

    public function test_pickups_sheet_includes_only_pickup_orders(): void
    {
        $pickup = ShopOrder::factory()->paid()->create([
            'name' => 'Pickup Customer',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => '2026-06-10',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $pickup->id]);

        $payAtPickup = ShopOrder::factory()->create([
            'name' => 'Pay at pickup',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
            'expected_delivery_date' => '2026-06-10',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $payAtPickup->id]);

        $delivery = ShopOrder::factory()->paid()->create([
            'name' => 'Delivery (ignored)',
            'fulfillment_mode' => 'delivery',
        ]);
        ShopOrderItem::factory()->create(['shop_order_id' => $delivery->id]);

        $sheet = new ShopOrderPickupsSheet(Collection::make([
            $pickup->fresh(['items.product.producer.translations']),
            $payAtPickup->fresh(['items.product.producer.translations']),
            $delivery->fresh(['items.product.producer.translations']),
        ]));
        $rows = $sheet->array();

        $this->assertCount(2, $rows);
        $payAtPickupRow = collect($rows)->firstWhere(5, $payAtPickup->id);
        $this->assertNotNull($payAtPickupRow);
        $this->assertSame(__('shop.export.payment_status_pay_at_pickup'), $payAtPickupRow[8]);
    }

    public function test_summary_sheet_echoes_filter_and_reports_kpis(): void
    {
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 5.0,
            'buying_price_cents' => 200,
        ]);
        $b2c = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'delivery',
            'customer_type' => CustomerType::Private,
            'total_amount_eur' => 17.9,
            'items_total_eur' => 15.0,
            'delivery_fee_eur' => 2.9,
            'delivery_fee_vat_rate_percent' => 19,
            'delivery_fee_vat_amount_eur' => 0.46,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $b2c->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit' => 'piece',
            'line_total_eur' => 15.0,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 0.98,
        ]);

        $b2bUnpaid = ShopOrder::factory()->create([
            'fulfillment_mode' => 'pickup',
            'customer_type' => CustomerType::Business,
            'payment_method' => 'in_person',
            'status' => ShopOrderStatus::PendingPayment,
            'total_amount_eur' => 20.0,
            'items_total_eur' => 20.0,
            'delivery_fee_eur' => 0.0,
            'delivery_fee_vat_rate_percent' => 0,
            'delivery_fee_vat_amount_eur' => 0,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $b2bUnpaid->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit' => 'piece',
            'line_total_eur' => 20.0,
            'vat_rate_snapshot_percent' => 7,
            'vat_amount_eur' => 1.31,
        ]);

        $orders = Collection::make([
            $b2c->fresh(['items.product.producer.translations']),
            $b2bUnpaid->fresh(['items.product.producer.translations']),
        ]);
        $sheet = new ShopOrderSummarySheet($orders, [
            'status' => 'finalized',
            'delivery_from' => '2026-06-10',
            'delivery_to' => '2026-06-12',
        ]);
        $rows = collect($sheet->array());

        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_filter_from') && $r[1] === '2026-06-10'));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_total_orders') && $r[1] === 2));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_delivery_orders') && $r[1] === 1));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_pickup_orders') && $r[1] === 1));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_b2c') && $r[1] === 1));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_b2b') && $r[1] === 1));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_grand_total') && $r[1] === 37.9));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_outstanding_in_person') && $r[1] === 20.0));
        // Buying: 2.00 € × 7 pieces = 14.00 → margin 35 - 14 = 21.00.
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_buying_cost') && $r[1] === 14.0));
        $this->assertTrue($rows->contains(fn (array $r) => $r[0] === __('shop.export.summary_label_gross_margin') && $r[1] === 21.0));
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

        $response->assertSee('href="'.route('app.admin.shop-orders.index').'/export', false);
        $response->assertSee('status='.ShopOrderStatus::Paid->value, false);
        $response->assertSee('delivery_from=2026-06-10', false);
        $response->assertSee('delivery_to=2026-06-12', false);
    }
}
