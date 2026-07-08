<?php

namespace App\Exports;

use App\Enums\ProductUnit;
use App\Exports\Concerns\FormatsShopOrders;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Aggregated supply view: total quantity needed per producer × product across
 * the filtered orders, B2C and B2B combined. Includes procurement cost +
 * margin estimates so purchasing can sanity-check the order against the
 * buying prices on file. Lines without a producer are grouped under a
 * "no producer" bucket. The revenue column is uniformly gross:
 * `line_total_eur` is gross on both order models.
 */
class ShopOrderFarmTotalsSheet implements FromArray, WithHeadings, WithTitle
{
    use FormatsShopOrders;

    /**
     * @param  Collection<int, ShopOrder>  $orders
     * @param  Collection<int, RestaurantOrder>  $restaurantOrders
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly Collection $restaurantOrders = new Collection,
    ) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_farms');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_producer'),
            (string) __('shop.export.col_product'),
            (string) __('shop.export.col_quantity'),
            (string) __('shop.export.col_unit'),
            (string) __('shop.export.col_order_count'),
            (string) __('shop.export.col_customer_count'),
            (string) __('shop.export.col_buying_price_per_unit_eur'),
            (string) __('shop.export.col_buying_total_eur'),
            (string) __('shop.export.col_revenue_eur'),
            (string) __('shop.export.col_margin_eur'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        /**
         * Bucket keyed by `producerName||productName||displayUnit`. Order
         * tokens are namespaced (`s{id}` / `r{id}`) so a shop order and a
         * restaurant order sharing a numeric id count as two orders.
         *
         * @var array<string, array{producer:string, product:string, qty_canonical:float, unit:?ProductUnit, display:string, unit_label_override:string, order_ids:array<string,true>, emails:array<string,true>, buying_per_unit:?float, revenue:float}>
         */
        $buckets = [];

        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $this->accumulate($buckets, $item, 's'.$order->id, $order->email);
            }
        }

        // B2B demand: only lines that will actually ship (declined lines and
        // not-yet-accepted request lines are excluded).
        foreach ($this->restaurantOrders as $order) {
            foreach ($order->items as $item) {
                if (! $item->isConfirmedLine()) {
                    continue;
                }
                $this->accumulate($buckets, $item, 'r'.$order->id, $order->user?->email);
            }
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $unit = $bucket['unit'];
            $display = $bucket['display'];
            if ($unit instanceof ProductUnit && $display !== '') {
                $factor = $unit->canonicalUnitsPerDisplay($display);
                $qty = $factor > 0 ? $bucket['qty_canonical'] / $factor : $bucket['qty_canonical'];
                $unitLabel = ProductUnit::displaySymbol($display);
            } else {
                $qty = $bucket['qty_canonical'];
                $unitLabel = $bucket['unit_label_override'];
            }

            $buyingPerUnit = $bucket['buying_per_unit'];
            $buyingTotal = $buyingPerUnit !== null ? round($buyingPerUnit * $qty, 2) : null;
            $revenue = round($bucket['revenue'], 2);
            $margin = $buyingTotal !== null ? round($revenue - $buyingTotal, 2) : null;

            $rows[] = [
                $bucket['producer'],
                $bucket['product'],
                round($qty, 4),
                $unitLabel,
                count($bucket['order_ids']),
                count($bucket['emails']),
                $buyingPerUnit,
                $buyingTotal,
                $revenue,
                $margin,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a[0], $a[1]] <=> [$b[0], $b[1]];
        });

        return $rows;
    }

    /**
     * Fold one order line (shop or restaurant) into the shared buckets.
     * Custom lines (no product) carry their free-text unit as the label.
     *
     * @param  array<string, array<string, mixed>>  $buckets
     */
    private function accumulate(array &$buckets, object $item, string $orderToken, ?string $email): void
    {
        $product = $item->product;
        $producerName = $product?->producer?->name
            ?: (string) __('shop.export.no_producer');
        $productName = $product?->name
            ?? ($item->displayName() ?: (string) __('shop.admin.deleted_product'));
        $unit = $product?->unit;
        $display = $product?->price_display_unit
            ?? $unit?->defaultDisplayUnit()
            ?? ($item->unit ?? '');

        $key = $producerName.'||'.$productName.'||'.$display;
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'producer' => $producerName,
                'product' => $productName,
                'qty_canonical' => 0.0,
                'unit' => $unit,
                'display' => $display,
                'unit_label_override' => $unit === null && $display !== '' ? ProductUnit::displaySymbol($display) : '',
                'order_ids' => [],
                'emails' => [],
                'buying_per_unit' => $this->buyingPricePerDisplayUnit($product),
                'revenue' => 0.0,
            ];
        }

        $buckets[$key]['qty_canonical'] += (float) $item->quantity;
        $buckets[$key]['order_ids'][$orderToken] = true;
        if ($email !== null && $email !== '') {
            $buckets[$key]['emails'][strtolower($email)] = true;
        }
        $buckets[$key]['revenue'] += (float) $item->line_total_eur;
    }
}
