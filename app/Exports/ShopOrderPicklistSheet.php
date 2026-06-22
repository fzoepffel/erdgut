<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsShopOrders;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Per-order delivery sheet — one row per {@see ShopOrder}, with the
 * positions stacked inside their own column (one product per line in a
 * single cell). All other order info (customer, address, totals, payment)
 * stays single-cell so a printed copy reads as one logical card per order.
 *
 * Quantities convert from canonical (g, ml) to the product's display unit
 * (kg, L) so packers don't have to translate between catalog and warehouse.
 */
class ShopOrderPicklistSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    use FormatsShopOrders;

    private const COL_POSITIONS = 'J';

    private const COL_QUANTITY = 'K';

    private const COL_UNIT_PRICE = 'L';

    private const COL_LINE_TOTAL = 'M';

    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_orders');
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
            (string) __('shop.export.col_phone'),
            (string) __('shop.export.col_address'),
            (string) __('shop.export.col_pickup_location'),
            (string) __('shop.export.col_notes'),
            (string) __('shop.export.col_positions'),
            (string) __('shop.export.col_quantity'),
            (string) __('shop.export.col_unit_price_eur'),
            (string) __('shop.export.col_line_total_eur'),
            (string) __('shop.export.col_items_total_eur'),
            (string) __('shop.export.col_delivery_fee_eur'),
            (string) __('shop.export.col_discount_eur'),
            (string) __('shop.export.col_coupon'),
            (string) __('shop.export.col_total_eur'),
            (string) __('shop.export.col_payment_method'),
            (string) __('shop.export.col_status'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            $positions = [];
            $quantities = [];
            $unitPrices = [];
            $lineTotals = [];

            foreach ($order->items as $item) {
                $product = $item->product;
                $name = $product?->name ?? (string) __('shop.admin.deleted_product');
                // Surface the variant grade alongside the product name so
                // packers can tell STANDARD vs YDA on a multi-variant order.
                if ($item->variant_name_snapshot !== null && $item->variant_name_snapshot !== '') {
                    $name .= ' · '.$item->variant_name_snapshot;
                }
                $producer = $product?->producer?->name;
                $positions[] = $producer ? sprintf('%s (%s)', $name, $producer) : $name;

                [$qtyDisplay, $unitLabel] = $this->displayQuantity(
                    $product?->unit,
                    $product?->price_display_unit,
                    (float) $item->quantity,
                );
                $quantities[] = trim(number_format($qtyDisplay, 2, '.', '').' '.$unitLabel);

                $unitPriceDisplay = $product?->unit !== null
                    ? round(
                        (float) $item->unit_price_snapshot_eur
                            * $product->unit->canonicalUnitsPerDisplay(
                                $product->price_display_unit ?? $product->unit->defaultDisplayUnit()
                            ),
                        2,
                    )
                    : round((float) $item->unit_price_snapshot_eur, 2);
                $unitPrices[] = number_format($unitPriceDisplay, 2, '.', '');
                $lineTotals[] = number_format((float) $item->line_total_eur, 2, '.', '');
            }

            $rows[] = [
                $order->id,
                $order->expected_delivery_date?->format('Y-m-d') ?? '',
                $this->windowLabel($order),
                $order->name ?? '',
                $this->customerTypeLabel($order->customer_type),
                $order->phone ?? '',
                $this->addressLine($order),
                $this->pickupLocationLabel($order->pickup_location),
                $order->delivery_notes ?? '',
                implode("\n", $positions),
                implode("\n", $quantities),
                implode("\n", $unitPrices),
                implode("\n", $lineTotals),
                (float) $order->items_total_eur,
                (float) $order->delivery_fee_eur,
                (float) $order->discount_eur,
                $order->coupon_code ?? '',
                (float) $order->total_amount_eur,
                $this->paymentMethodLabel($order->payment_method),
                $this->statusLabel($order->status),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        return $rows;
    }

    /**
     * Top-align + wrap-text the whole data range so the stacked-position
     * columns render as one cell with line breaks, and the single-value
     * neighbours sit at the top of the row instead of vertically centered.
     */
    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 2) {
            return [];
        }

        $sheet->getStyle('A2:T'.$highestRow)
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        // Right-align the four numeric multi-line columns so figures stack tidily.
        foreach ([self::COL_UNIT_PRICE, self::COL_LINE_TOTAL] as $col) {
            $sheet->getStyle($col.'2:'.$col.$highestRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 8,   // order #
            'B' => 12,  // date
            'C' => 14,  // window
            'D' => 22,  // customer
            'E' => 10,  // type
            'F' => 16,  // phone
            'G' => 30,  // address
            'H' => 18,  // pickup
            'I' => 22,  // notes
            'J' => 36,  // positions (multi-line)
            'K' => 14,  // quantity (multi-line)
            'L' => 12,  // unit price (multi-line)
            'M' => 12,  // line total (multi-line)
            'N' => 12,  // items total
            'O' => 12,  // delivery fee
            'P' => 10,  // discount
            'Q' => 12,  // coupon
            'R' => 12,  // total
            'S' => 12,  // payment
            'T' => 14,  // status
        ];
    }
}
