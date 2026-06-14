<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsShopOrders;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Per-line export so packers can work order-by-order. Quantities convert
 * from canonical storage (g, ml) to the product's display unit (kg, L) so
 * Excel totals read the way the catalog does. Sorted by delivery date →
 * order id → product name so the printout matches the route.
 */
class ShopOrderPicklistSheet implements FromArray, WithHeadings, WithTitle
{
    use FormatsShopOrders;

    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_picklist');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_order_id'),
            (string) __('shop.export.col_delivery_date'),
            (string) __('shop.export.col_window'),
            (string) __('shop.export.col_customer_name'),
            (string) __('shop.export.col_customer_type'),
            (string) __('shop.export.col_product'),
            (string) __('shop.export.col_producer'),
            (string) __('shop.export.col_quantity'),
            (string) __('shop.export.col_unit'),
            (string) __('shop.export.col_unit_price_eur'),
            (string) __('shop.export.col_line_total_eur'),
            (string) __('shop.export.col_vat_percent'),
            (string) __('shop.export.col_vat_eur'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                [$qtyDisplay, $unitLabel] = $this->displayQuantity(
                    $product?->unit,
                    $product?->price_display_unit,
                    (float) $item->quantity,
                );

                // €/display-unit so packers don't see canonical €/g pricing.
                $unitPriceDisplay = $product?->unit !== null
                    ? round(
                        (float) $item->unit_price_snapshot_eur
                            * $product->unit->canonicalUnitsPerDisplay(
                                $product->price_display_unit ?? $product->unit->defaultDisplayUnit()
                            ),
                        4,
                    )
                    : (float) $item->unit_price_snapshot_eur;

                $rows[] = [
                    $order->id,
                    $order->expected_delivery_date?->format('Y-m-d') ?? '',
                    $this->windowLabel($order),
                    $order->name ?? '',
                    $this->customerTypeLabel($order->customer_type),
                    $product?->name ?? (string) __('shop.admin.deleted_product'),
                    $product?->producer?->name ?? '',
                    $qtyDisplay,
                    $unitLabel,
                    $unitPriceDisplay,
                    (float) $item->line_total_eur,
                    (int) $item->vat_rate_snapshot_percent,
                    (float) $item->vat_amount_eur,
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            return [$a[1], $a[0], $a[5]] <=> [$b[1], $b[0], $b[5]];
        });

        return $rows;
    }
}
