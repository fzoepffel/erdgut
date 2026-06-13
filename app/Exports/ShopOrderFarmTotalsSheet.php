<?php

namespace App\Exports;

use App\Enums\ProductUnit;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Aggregated supply view: total quantity needed per producer × product across
 * the filtered orders, in the product's display unit. Lines without a producer
 * are grouped under a "no producer" bucket so nothing disappears.
 */
class ShopOrderFarmTotalsSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

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
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        /**
         * Bucket keyed by `producerName||productName||displayUnit` so the
         * sheet aggregates the same product across orders and keeps display
         * units consistent (no mixed kg + g rows for the same product).
         *
         * @var array<string, array{producer:string, product:string, qty_canonical:float, unit:?ProductUnit, display:string, order_ids:array<int,true>}>
         */
        $buckets = [];

        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                $producerName = $product?->producer?->name
                    ?: (string) __('shop.export.no_producer');
                $productName = $product?->name
                    ?? (string) __('shop.admin.deleted_product');
                $unit = $product?->unit;
                $display = $product?->price_display_unit
                    ?? $unit?->defaultDisplayUnit()
                    ?? '';

                $key = $producerName.'||'.$productName.'||'.$display;
                if (! isset($buckets[$key])) {
                    $buckets[$key] = [
                        'producer' => $producerName,
                        'product' => $productName,
                        'qty_canonical' => 0.0,
                        'unit' => $unit,
                        'display' => $display,
                        'order_ids' => [],
                    ];
                }

                $buckets[$key]['qty_canonical'] += (float) $item->quantity;
                $buckets[$key]['order_ids'][$order->id] = true;
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
                $unitLabel = '';
            }

            $rows[] = [
                $bucket['producer'],
                $bucket['product'],
                round($qty, 4),
                $unitLabel,
                count($bucket['order_ids']),
            ];
        }

        // Sort by producer, then product — predictable scanning for procurement.
        usort($rows, function (array $a, array $b): int {
            return [$a[0], $a[1]] <=> [$b[0], $b[1]];
        });

        return $rows;
    }
}
