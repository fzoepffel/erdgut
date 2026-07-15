<?php

namespace App\Exports;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Exports\Concerns\FormatsShopOrders;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * KPI cover sheet. Self-describes the filter applied, then summarizes
 * counts, revenue, VAT collected, procurement cost + margin, outstanding
 * pay-at-delivery balance, and the top-10 products by quantity / revenue.
 * Numbers are stored as raw floats/ints so the recipient can pivot them.
 */
class ShopOrderSummarySheet implements FromArray, WithTitle
{
    use FormatsShopOrders;

    /**
     * @param  Collection<int, ShopOrder>  $orders
     * @param  array{status: string, delivery_from: string, delivery_to: string}  $filter
     * @param  Collection<int, RestaurantOrder>  $restaurantOrders
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly array $filter,
        private readonly Collection $restaurantOrders = new Collection,
    ) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_summary');
    }

    /**
     * Two-column "label, value" sheet — easier for ops to scan than a wide
     * single-row dashboard, and easier to print.
     *
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [
            [(string) __('shop.export.summary_heading'), (string) __('shop.export.summary_heading_value', ['date' => now()->format('Y-m-d H:i')])],
            ['', ''],
            [(string) __('shop.export.summary_filter_status'), $this->filterStatusLabel()],
            [(string) __('shop.export.summary_filter_from'), $this->filter['delivery_from'] ?: '—'],
            [(string) __('shop.export.summary_filter_to'), $this->filter['delivery_to'] ?: '—'],
            ['', ''],
        ];

        $rows[] = [(string) __('shop.export.summary_section_counts'), ''];
        $rows[] = [(string) __('shop.export.summary_label_total_orders'), $this->orders->count()];
        $rows[] = [
            (string) __('shop.export.summary_label_delivery_orders'),
            $this->orders->where('fulfillment_mode', 'delivery')->count(),
        ];
        $rows[] = [
            (string) __('shop.export.summary_label_pickup_orders'),
            $this->orders->where('fulfillment_mode', 'pickup')->count(),
        ];
        $rows[] = [
            (string) __('shop.export.summary_label_b2c'),
            $this->orders->filter(fn (ShopOrder $o) => $o->customer_type === CustomerType::Private)->count(),
        ];
        $rows[] = [
            (string) __('shop.export.summary_label_b2b'),
            $this->orders->filter(fn (ShopOrder $o) => $o->customer_type === CustomerType::Business)->count(),
        ];
        $rows[] = ['', ''];

        $itemsTotal = round((float) $this->orders->sum(fn (ShopOrder $o) => (float) $o->items_total_eur), 2);
        $deliveryFees = round((float) $this->orders->sum(fn (ShopOrder $o) => (float) $o->delivery_fee_eur), 2);
        $discounts = round((float) $this->orders->sum(fn (ShopOrder $o) => (float) $o->discount_eur), 2);
        $grandTotal = round((float) $this->orders->sum(fn (ShopOrder $o) => (float) $o->total_amount_eur), 2);

        $rows[] = [(string) __('shop.export.summary_section_revenue'), ''];
        $rows[] = [(string) __('shop.export.summary_label_items_total'), $itemsTotal];
        $rows[] = [(string) __('shop.export.summary_label_delivery_fees'), $deliveryFees];
        $rows[] = [(string) __('shop.export.summary_label_discounts'), $discounts];
        $rows[] = [(string) __('shop.export.summary_label_grand_total'), $grandTotal];
        $rows[] = ['', ''];

        $rows[] = [(string) __('shop.export.summary_section_vat'), ''];
        foreach ($this->vatByRate() as $rate => $amount) {
            $rows[] = [
                (string) __('shop.export.summary_label_vat_at', ['rate' => $rate]),
                round($amount, 2),
            ];
        }
        $rows[] = ['', ''];

        [$buyingCost, $margin] = $this->procurementTotals();
        $rows[] = [(string) __('shop.export.summary_section_procurement'), ''];
        $rows[] = [(string) __('shop.export.summary_label_buying_cost'), $buyingCost];
        $rows[] = [(string) __('shop.export.summary_label_gross_margin'), $margin];
        $rows[] = ['', ''];

        $outstanding = $this->orders
            ->filter(fn (ShopOrder $o) => $o->payment_method === 'in_person'
                && $o->status === ShopOrderStatus::PendingPayment)
            ->sum(fn (ShopOrder $o) => (float) $o->total_amount_eur);

        $rows[] = [(string) __('shop.export.summary_section_outstanding'), ''];
        $rows[] = [(string) __('shop.export.summary_label_outstanding_in_person'), round((float) $outstanding, 2)];
        $rows[] = ['', ''];

        // B2B stays its own block: restaurant prices are net, blending them
        // into the gross B2C figures above would mislead.
        if ($this->restaurantOrders->isNotEmpty()) {
            $confirmedLines = $this->restaurantOrders
                ->flatMap(fn ($o) => $o->items)
                ->filter(fn ($i) => $i->isConfirmedLine());

            $rows[] = [(string) __('shop.export.summary_section_b2b_restaurant'), ''];
            $rows[] = [(string) __('shop.export.summary_label_b2b_order_count'), $this->restaurantOrders->count()];
            $rows[] = [
                (string) __('shop.export.summary_label_b2b_net_revenue'),
                round((float) $this->restaurantOrders->sum(fn ($o) => (float) $o->items_total_net_eur), 2),
            ];
            $rows[] = [(string) __('shop.export.summary_label_b2b_line_count'), $confirmedLines->count()];
            $rows[] = ['', ''];
        }

        [$byQuantity, $byRevenue] = $this->topProducts();
        $rows[] = [(string) __('shop.export.summary_label_top_by_quantity'), ''];
        foreach ($byQuantity as $entry) {
            $rows[] = [$entry['name'], $entry['display']];
        }
        $rows[] = ['', ''];
        $rows[] = [(string) __('shop.export.summary_label_top_by_revenue'), ''];
        foreach ($byRevenue as $entry) {
            $rows[] = [$entry['name'], round($entry['revenue'], 2)];
        }

        return $rows;
    }

    private function filterStatusLabel(): string
    {
        return match ($this->filter['status']) {
            'finalized' => (string) __('shop.admin.status_finalized'),
            'all' => (string) __('All'),
            default => (string) __('shop.admin.status_'.$this->filter['status']),
        };
    }

    /**
     * @return array<int, float> percent ⇒ summed VAT euro
     */
    private function vatByRate(): array
    {
        $byRate = [];
        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $rate = (int) $item->vat_rate_snapshot_percent;
                $byRate[$rate] = ($byRate[$rate] ?? 0.0) + (float) $item->vat_amount_eur;
            }
            $deliveryRate = (int) $order->delivery_fee_vat_rate_percent;
            if ($deliveryRate > 0) {
                $byRate[$deliveryRate] = ($byRate[$deliveryRate] ?? 0.0)
                    + (float) $order->delivery_fee_vat_amount_eur;
            }
        }
        ksort($byRate);

        return $byRate;
    }

    /**
     * @return array{0: float, 1: float} buying cost + revenue − cost margin
     */
    private function procurementTotals(): array
    {
        $buying = 0.0;
        $revenue = 0.0;
        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $revenue += (float) $item->line_total_eur;
                $lineBuying = $this->buyingTotalForLine($item);
                if ($lineBuying !== null) {
                    $buying += $lineBuying;
                }
            }
        }

        return [round($buying, 2), round($revenue - $buying, 2)];
    }

    /**
     * Top-10 products by aggregated display-unit quantity and by revenue.
     *
     * @return array{0: list<array{name:string, display:string}>, 1: list<array{name:string, revenue:float}>}
     */
    private function topProducts(): array
    {
        $byProduct = [];
        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                $name = $product?->name ?? (string) __('shop.admin.deleted_product');
                $unit = $product?->unit;
                $display = $product?->price_display_unit
                    ?? $unit?->defaultDisplayUnit()
                    ?? '';
                $key = $name.'||'.$display;
                if (! isset($byProduct[$key])) {
                    $byProduct[$key] = [
                        'name' => $name,
                        'unit' => $unit,
                        'display' => $display,
                        'count_label' => in_array($item->product?->unit, [ProductUnit::Piece, ProductUnit::Bunch], true) ? $item->product->count_unit_label : null,
                        'qty_canonical' => 0.0,
                        'revenue' => 0.0,
                    ];
                }
                $byProduct[$key]['qty_canonical'] += (float) $item->quantity;
                $byProduct[$key]['revenue'] += (float) $item->line_total_eur;
            }
        }

        $rows = array_map(static function (array $row): array {
            $unit = $row['unit'];
            $display = $row['display'];
            if ($unit instanceof ProductUnit && $display !== '') {
                $factor = $unit->canonicalUnitsPerDisplay($display);
                $qty = $factor > 0 ? $row['qty_canonical'] / $factor : $row['qty_canonical'];
                $word = ($row['count_label'] ?? null)?->label($qty) ?? ProductUnit::displaySymbol($display);
                $label = number_format($qty, 2).' '.$word;
            } else {
                $qty = $row['qty_canonical'];
                $label = (string) $qty;
            }

            return [
                'name' => $row['name'],
                'qty' => $qty,
                'display' => $label,
                'revenue' => $row['revenue'],
            ];
        }, $byProduct);

        usort($rows, fn (array $a, array $b): int => $b['qty'] <=> $a['qty']);
        $byQuantity = array_map(
            static fn (array $r): array => ['name' => $r['name'], 'display' => $r['display']],
            array_slice($rows, 0, 10),
        );

        usort($rows, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
        $byRevenue = array_map(
            static fn (array $r): array => ['name' => $r['name'], 'revenue' => $r['revenue']],
            array_slice($rows, 0, 10),
        );

        return [$byQuantity, $byRevenue];
    }
}
