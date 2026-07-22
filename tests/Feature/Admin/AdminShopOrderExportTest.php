<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopOrderStatus;
use App\Exports\ShopOrderFarmTotalsSheet;
use App\Exports\ShopOrderPicklistSheet;
use App\Exports\ShopOrderPickupsSheet;
use App\Exports\ShopOrderRouteSheet;
use App\Exports\ShopOrdersExport;
use App\Exports\ShopOrderSummarySheet;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductProducerVariantPrice;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Coverage for the five-sheet shop-orders export. Asserts auth gating, that
 * the filter is reused for the file, and the shape of each individual sheet
 * (orders w/ stacked positions, farm totals, route, pickups, KPI summary).
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
            $picklist = $sheets[1];
            self::assertInstanceOf(ShopOrderPicklistSheet::class, $picklist);
            $ids = collect($picklist->array())->pluck(0)->all();

            return in_array($inRange->id, $ids, true)
                && ! in_array($outside->id, $ids, true)
                && ! in_array($abandoned->id, $ids, true);
        });
    }

    public function test_workbook_exposes_five_sheets_in_canonical_order(): void
    {
        $export = new ShopOrdersExport(
            Collection::make(),
            ['status' => 'finalized', 'delivery_from' => '', 'delivery_to' => ''],
        );
        $sheets = $export->sheets();

        $this->assertCount(5, $sheets);
        $this->assertInstanceOf(ShopOrderSummarySheet::class, $sheets[0]);
        $this->assertInstanceOf(ShopOrderPicklistSheet::class, $sheets[1]);
        $this->assertInstanceOf(ShopOrderFarmTotalsSheet::class, $sheets[2]);
        $this->assertInstanceOf(ShopOrderRouteSheet::class, $sheets[3]);
        $this->assertInstanceOf(ShopOrderPickupsSheet::class, $sheets[4]);
    }

    public function test_picklist_sheet_emits_one_row_per_order_with_stacked_positions(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farmA->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
        ]);
        $eggs = Product::factory()->create([
            'producer_id' => $farmB->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
        ]);

        $order = ShopOrder::factory()->paid()->create([
            'name' => 'Anna Kunde',
            'customer_type' => CustomerType::Private,
            'fulfillment_mode' => 'delivery',
            'expected_delivery_date' => '2026-06-10',
            'delivery_window_from' => '14:00:00',
            'delivery_window_to' => '16:00:00',
            'delivery_street' => 'Karl-Marx-Allee 1',
            'delivery_postal_code' => '10178',
            'delivery_city' => 'Berlin',
            'delivery_notes' => 'Ring twice',
            'items_total_eur' => 23.5,
            'delivery_fee_eur' => 2.9,
            'discount_eur' => 0,
            'total_amount_eur' => 26.4,
        ]);
        // 1500 g canonical → 1.5 kg display @ 9 €/kg = 13.50 €.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'quantity' => 1500,
            'unit' => 'g',
            'unit_price_snapshot_eur' => 0.009,
            'line_total_eur' => 13.5,
        ]);
        // 5 pieces × 2 €/piece = 10 €.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $eggs->id,
            'quantity' => 5,
            'unit' => 'piece',
            'unit_price_snapshot_eur' => 2.0,
            'line_total_eur' => 10.0,
        ]);

        $sheet = new ShopOrderPicklistSheet(
            Collection::make([$order->fresh(['items.product.producer.translations'])])
        );
        $rows = $sheet->array();

        // One row for the whole order.
        $this->assertCount(1, $rows);
        $row = $rows[0];

        // Order metadata sits in single cells.
        $this->assertSame($order->id, $row[0]);
        $this->assertSame('2026-06-10', $row[1]);
        $this->assertSame('14:00 – 16:00', $row[2]);
        $this->assertSame('Anna Kunde', $row[3]);
        $this->assertSame(__('shop.export.type_private'), $row[4]);
        $this->assertSame('Karl-Marx-Allee 1, 10178 Berlin', $row[6]);
        $this->assertSame('Ring twice', $row[8]);

        // Position-related columns are \n-joined so Excel can wrap them.
        $this->assertSame(
            sprintf("%s (%s)\n%s (%s)", $tomato->name, $farmA->name, $eggs->name, $farmB->name),
            $row[9],
        );
        $this->assertSame('1.50 kg'."\n".'5.00 '.__('units.piece'), $row[10]);
        $this->assertSame("9.00\n2.00", $row[11]);
        $this->assertSame("13.50\n10.00", $row[12]);

        // Order-level totals stay single numeric cells.
        $this->assertSame(23.5, $row[13]);
        $this->assertSame(2.9, $row[14]);
        $this->assertSame(0.0, $row[15]);
        $this->assertSame(26.4, $row[17]);
    }

    public function test_picklist_positions_include_variant_snapshot_when_present(): void
    {
        $farm = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farm->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'bornsdorf',
            'expected_delivery_date' => '2026-06-10',
        ]);

        // Two lines for the same product on the order, distinct grades.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'variant_id' => null,
            'variant_name_snapshot' => 'STANDARD',
            'quantity' => 1000,
            'unit' => 'g',
            'unit_price_snapshot_eur' => 0.009,
            'line_total_eur' => 9.0,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'variant_id' => null,
            'variant_name_snapshot' => 'YDA',
            'quantity' => 500,
            'unit' => 'g',
            'unit_price_snapshot_eur' => 0.012,
            'line_total_eur' => 6.0,
        ]);

        $sheet = new ShopOrderPicklistSheet(
            Collection::make([$order->fresh(['items.product.producer.translations'])])
        );
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $expected = sprintf(
            "%s · STANDARD (%s)\n%s · YDA (%s)",
            $tomato->name,
            $farm->name,
            $tomato->name,
            $farm->name,
        );
        $this->assertSame($expected, $rows[0][9]);
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

    public function test_farm_totals_costs_each_share_at_its_farms_buying_price(): void
    {
        // Farm A (primary, default 3 €/kg) has 1 kg in stock, farm B
        // (override 2 €/kg) has 5 kg. A 2.5 kg order: A drained first
        // (equal anchor/breadth, lower id) → cost 1×3 + 1.5×2 = 6,
        // weighted per-unit 6 / 2.5 = 2.4.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farmA->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.006,
            'buying_price_cents' => 300,
        ]);
        $tomato->syncProducers([$farmA->id, $farmB->id]);
        $tomato->producers()->updateExistingPivot($farmB->id, ['buying_price_cents' => 200]);
        foreach ([[$farmA->id, 1000], [$farmB->id, 5000]] as [$farmId, $qty]) {
            ProductStockInflow::create([
                'product_id' => $tomato->id, 'producer_id' => $farmId,
                'received_on' => '2026-06-10', 'quantity' => $qty, 'freshness_window_days' => 2,
            ]);
        }

        $order = ShopOrder::factory()->paid()->create([
            'email' => 'a@example.com',
            'expected_delivery_date' => '2026-06-10',
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'quantity' => 2500,
            'unit' => 'g',
            'line_total_eur' => 15.0,
        ]);

        $rows = collect((new ShopOrderFarmTotalsSheet(Collection::make([
            $order->fresh(['items.product.producer.translations']),
        ])))->array());

        $row = $rows->firstWhere(1, $tomato->name);
        $this->assertNotNull($row);
        $this->assertSame(2.5, $row[2]);
        $this->assertEqualsWithDelta(2.4, $row[6], 0.001); // weighted €/kg
        $this->assertEqualsWithDelta(6.0, $row[7], 0.001); // buying total
        $this->assertEqualsWithDelta(15.0, $row[8], 0.001);
        $this->assertEqualsWithDelta(9.0, $row[9], 0.001); // margin
        // Both farms named in the split label.
        $this->assertStringContainsString($farmA->name, (string) $row[0]);
        $this->assertStringContainsString($farmB->name, (string) $row[0]);
    }

    public function test_farm_totals_costs_grade_shares_at_the_grade_price(): void
    {
        // The named grade comes only from farm B, which charges 4 euro/kg
        // for THIS grade (farm price 2, default 3). 2 kg of the grade →
        // buying total 8.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farmA->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.006,
            'buying_price_cents' => 300,
        ]);
        $tomato->syncProducers([$farmA->id, $farmB->id]);
        $tomato->producers()->updateExistingPivot($farmB->id, ['buying_price_cents' => 200]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $tomato->id, 'name' => ['de' => 'Premium'],
        ]);
        ProductProducerVariantPrice::create([
            'product_id' => $tomato->id, 'producer_id' => $farmB->id,
            'variant_id' => $variant->id, 'buying_price_cents' => 400,
        ]);
        ProductStockInflow::create([
            'product_id' => $tomato->id, 'producer_id' => $farmB->id, 'variant_id' => $variant->id,
            'received_on' => '2026-06-10', 'quantity' => 5000, 'freshness_window_days' => 2,
        ]);

        $order = ShopOrder::factory()->paid()->create([
            'email' => 'a@example.com',
            'expected_delivery_date' => '2026-06-10',
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'variant_id' => $variant->id,
            'quantity' => 2000,
            'unit' => 'g',
            'line_total_eur' => 16.0,
        ]);

        $rows = collect((new ShopOrderFarmTotalsSheet(Collection::make([
            $order->fresh(['items.product.producer.translations']),
        ])))->array());

        $row = $rows->first(fn ($r) => str_contains((string) $r[1], $tomato->name));
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(4.0, $row[6], 0.001);  // grade price €/kg
        $this->assertEqualsWithDelta(8.0, $row[7], 0.001);  // buying total
        $this->assertEqualsWithDelta(8.0, $row[9], 0.001);  // margin 16 − 8
    }

    public function test_exports_use_the_count_unit_word(): void
    {
        app()->setLocale('de');
        $farm = Producer::factory()->create();
        $basil = Product::factory()->create([
            'producer_id' => $farm->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 3.0,
            'count_unit_label' => 'pot',
        ]);

        $order = ShopOrder::factory()->paid()->create(['email' => 'a@example.com']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $basil->id,
            'quantity' => 2,
            'unit' => 'piece',
            'line_total_eur' => 6.0,
        ]);

        $rows = collect((new ShopOrderFarmTotalsSheet(Collection::make([
            $order->fresh(['items.product.producer.translations']),
        ])))->array());

        $row = $rows->firstWhere(1, $basil->name);
        $this->assertNotNull($row);
        $this->assertSame(2.0, $row[2]);
        $this->assertSame('Töpfe', $row[3]); // plural word instead of "Stück"
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

    public function test_farmer_export_requires_admin(): void
    {
        // Guest → 401 from the JSON middleware on the producers endpoint;
        // a logged-in non-admin → 403 from the admin gate.
        $this->getJson(route('app.admin.shop-orders.farmer-export.producers'))->assertUnauthorized();
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)
            ->getJson(route('app.admin.shop-orders.farmer-export.producers'))
            ->assertForbidden();
    }

    public function test_farmer_export_producers_lists_only_producers_in_filter(): void
    {
        $admin = User::factory()->admin()->create();
        // Producer A: has an order in range. Producer B: has one outside.
        $producerA = Producer::factory()->create();
        $producerB = Producer::factory()->create();
        $productA = Product::factory()->create([
            'producer_id' => $producerA->id,
            'unit' => ProductUnit::Mass->value,
        ]);
        $productB = Product::factory()->create([
            'producer_id' => $producerB->id,
            'unit' => ProductUnit::Mass->value,
        ]);

        $inWindow = ShopOrder::factory()->paid()->create(['expected_delivery_date' => '2026-06-10']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $inWindow->id,
            'product_id' => $productA->id,
        ]);
        $outOfWindow = ShopOrder::factory()->paid()->create(['expected_delivery_date' => '2026-07-30']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $outOfWindow->id,
            'product_id' => $productB->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('app.admin.shop-orders.farmer-export.producers', [
                'delivery_from' => '2026-06-01',
                'delivery_to' => '2026-06-30',
            ]));
        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($producerA->id, $ids);
        $this->assertNotContains($producerB->id, $ids);
    }

    public function test_farmer_export_returns_zip(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.01,
        ]);
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => '2026-06-10']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 3000,
            'unit' => 'g',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.farmer-export', [
                'producer_id' => $producer->id,
                'delivery_from' => '2026-06-01',
                'delivery_to' => '2026-06-30',
            ]));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('erdgut-hofbestellung-', $response->headers->get('Content-Disposition'));
    }

    public function test_farmer_export_requires_producer_id(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.farmer-export'))
            ->assertStatus(422);
    }

    public function test_farmer_export_csv_shows_natural_units_and_buying_price(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        // Priced per 100 g, buying price 3,50 € per 100 g.
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => '100g',
            'unit_price' => 0.01,
            'buying_price_cents' => 350,
        ]);
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => '2026-06-10']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 30, // 30 g canonical
            'unit' => 'g',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.farmer-export', [
                'producer_id' => $producer->id,
                'delivery_from' => '2026-06-01',
                'delivery_to' => '2026-06-30',
            ]))->assertOk();

        $csv = $this->extractCsvFromZip($response->getFile()->getPathname());
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        // Header: Product, Price, Unit, Amount — no Variant column.
        $this->assertStringContainsString(__('shop.admin.farmer_export_col_price'), $lines[0]);
        $this->assertStringNotContainsString(__('shop.admin.farmer_export_col_variant'), $lines[0]);

        // Data row: 30 g in natural units (not "0,30 100 g"), buying price
        // per pricing unit. (Separators follow the request locale.)
        $cells = str_getcsv($lines[1]);
        $this->assertStringContainsString('€/100 g', $cells[2]); // price per pricing unit
        $this->assertStringContainsString('3', $cells[2]);
        $this->assertSame('g', $cells[3]);   // unit column
        $this->assertSame('30', $cells[4]);  // amount column
    }

    private function extractCsvFromZip(string $zipPath): string
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $csv = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with($name, '.csv')) {
                $csv = (string) $zip->getFromName($name);
                break;
            }
        }
        $zip->close();

        // Drop the UTF-8 BOM the export prepends for Excel.
        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    }

    // ── B2B restaurant demand in the workbook ─────────────────────────────

    /** A Confirmed B2B order for a stocked product (auto-confirms via the service). */
    private function confirmedRestaurantOrder(Product $product, float $quantity): RestaurantOrder
    {
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => Carbon::tomorrow()->toDateString(),
            'quantity' => 100000,
            'freshness_window_days' => 3,
        ]);
        $profile = RestaurantProfile::factory()->verified()->create();

        return app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity]],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'Gastroweg 7', 'delivery_postal_code' => '10405', 'delivery_city' => 'Berlin',
        ]);
    }

    public function test_export_includes_restaurant_orders_for_default_filter(): void
    {
        Excel::fake();
        $admin = User::factory()->admin()->create();
        Producer::factory()->create();
        $product = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece']);

        $shopOrder = ShopOrder::factory()->paid()->create(['expected_delivery_date' => Carbon::tomorrow()->toDateString()]);
        ShopOrderItem::factory()->create(['shop_order_id' => $shopOrder->id]);
        $restaurantOrder = $this->confirmedRestaurantOrder($product, 5);

        $this->actingAs($admin)->get(route('app.admin.shop-orders.export'))->assertOk();

        Excel::assertDownloaded('erdgut-bestellungen-'.now()->format('Y-m-d').'.xlsx', function (ShopOrdersExport $export) use ($shopOrder, $restaurantOrder): bool {
            $ids = collect($export->sheets()[1]->array())->pluck(0)->all();

            return in_array($shopOrder->id, $ids, true)
                && in_array('R'.$restaurantOrder->id, $ids, true);
        });
    }

    public function test_export_type_filter_scopes_restaurant_orders(): void
    {
        Excel::fake();
        $admin = User::factory()->admin()->create();
        Producer::factory()->create();
        $product = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece']);
        $restaurantOrder = $this->confirmedRestaurantOrder($product, 5);

        $filename = 'erdgut-bestellungen-'.now()->format('Y-m-d').'.xlsx';

        $this->actingAs($admin)->get(route('app.admin.shop-orders.export', ['type' => 'b2c']))->assertOk();
        Excel::assertDownloaded($filename, function (ShopOrdersExport $export) use ($restaurantOrder): bool {
            return ! in_array('R'.$restaurantOrder->id, collect($export->sheets()[1]->array())->pluck(0)->all(), true);
        });

        $this->actingAs($admin)->get(route('app.admin.shop-orders.export', ['type' => 'b2b']))->assertOk();
        Excel::assertDownloaded($filename, function (ShopOrdersExport $export) use ($restaurantOrder): bool {
            return in_array('R'.$restaurantOrder->id, collect($export->sheets()[1]->array())->pluck(0)->all(), true);
        });
    }

    public function test_picklist_restaurant_row_shape_excludes_declined_lines(): void
    {
        Producer::factory()->create();
        $product = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece']);
        $profile = RestaurantProfile::factory()->verified()->create();
        $svc = app(RestaurantOrderService::class);

        $order = $svc->submitRequest($profile->user, [
            'items' => [
                ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 4],
                ['name' => 'Wildkräuter', 'unit' => 'kg', 'quantity' => 2],
            ],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'Gastroweg 7', 'delivery_postal_code' => '10405', 'delivery_city' => 'Berlin',
        ]);
        [$catalogLine, $customLine] = $order->items()->orderBy('id')->get();
        $svc->acceptLine($catalogLine);
        $svc->declineLine($customLine);
        $this->assertTrue($order->refresh()->status === RestaurantOrderStatus::Confirmed);

        $sheet = new ShopOrderPicklistSheet(
            Collection::make(),
            Collection::make([$order->fresh(['items.product.producer.translations', 'user.restaurantProfile'])]),
        );
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('R'.$order->id, $row[0]);
        $this->assertSame($profile->business_name, $row[3]);
        $this->assertSame(__('shop.export.type_business'), $row[4]);
        $this->assertStringContainsString($product->name, $row[9]);
        $this->assertStringNotContainsString('Wildkräuter', $row[9]);
        $this->assertSame(__('shop.export.payment_invoice'), $row[18]);
    }

    public function test_farm_totals_merge_restaurant_demand_into_producer_buckets(): void
    {
        $farm = Producer::factory()->create();
        $tomato = Product::factory()->create([
            'producer_id' => $farm->id,
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.006,
            'buying_price_cents' => 300,
        ]);

        $shopOrder = ShopOrder::factory()->paid()->create(['email' => 'a@example.com']);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $tomato->id,
            'quantity' => 2000,
            'unit' => 'g',
            'line_total_eur' => 12.0,
        ]);
        $restaurantOrder = $this->confirmedRestaurantOrder($tomato, 500); // 0.5 kg

        $sheet = new ShopOrderFarmTotalsSheet(
            Collection::make([$shopOrder->fresh(['items.product.producer.translations'])]),
            Collection::make([$restaurantOrder->fresh(['items.product.producer.translations', 'user'])]),
        );
        $row = collect($sheet->array())->firstWhere(1, $tomato->name);

        $this->assertNotNull($row);
        $this->assertSame(2.5, $row[2]); // 2 kg B2C + 0.5 kg B2B
        $this->assertSame('kg', $row[3]);
        $this->assertSame(2, $row[4]); // one order per channel
        $this->assertSame(2, $row[5]); // two distinct customers
        $expectedRevenue = round(12.0 + (float) $restaurantOrder->items()->first()->line_total_eur, 2);
        $this->assertSame($expectedRevenue, $row[8]);
    }

    public function test_route_sheet_adds_restaurant_stop_with_zero_payment_due(): void
    {
        Producer::factory()->create();
        $product = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece']);
        $restaurantOrder = $this->confirmedRestaurantOrder($product, 5);

        $sheet = new ShopOrderRouteSheet(
            Collection::make(),
            Collection::make([$restaurantOrder->fresh(['items', 'user.restaurantProfile'])]),
        );
        $rows = $sheet->array();

        $this->assertCount(1, $rows);
        $this->assertSame('Gastroweg 7', $rows[0][2]);
        $this->assertSame('R'.$restaurantOrder->id, $rows[0][5]);
        $this->assertSame(0.0, $rows[0][10]); // invoice payment, nothing due at the door
        $this->assertSame(__('shop.export.type_business'), $rows[0][12]);
    }

    public function test_summary_reports_separate_b2b_block(): void
    {
        Producer::factory()->create();
        $product = Product::factory()->create(['unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece']);
        $restaurantOrder = $this->confirmedRestaurantOrder($product, 5);

        $sheet = new ShopOrderSummarySheet(
            Collection::make(),
            ['status' => 'finalized', 'delivery_from' => '', 'delivery_to' => ''],
            Collection::make([$restaurantOrder->fresh(['items'])]),
        );
        $rows = collect($sheet->array());

        $this->assertNotNull($rows->firstWhere(0, __('shop.export.summary_section_b2b_restaurant')));
        $this->assertSame(1, $rows->firstWhere(0, __('shop.export.summary_label_b2b_order_count'))[1]);
        $this->assertSame(
            round((float) $restaurantOrder->items_total_net_eur, 2),
            $rows->firstWhere(0, __('shop.export.summary_label_b2b_net_revenue'))[1],
        );
    }
}
