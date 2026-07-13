<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsShopOrders;
use App\Models\Producer;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use App\Services\Admin\ProducerAllocationService;
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
     * @param  Collection<int, RestaurantOrder>  $restaurantOrders
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly Collection $restaurantOrders = new Collection,
    ) {}

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
                $name = $item->displayName();
                // Surface the variant grade alongside the product name so
                // packers can tell STANDARD vs YDA on a multi-variant order.
                if ($item->variant_name_snapshot !== null && $item->variant_name_snapshot !== '') {
                    $name .= ' · '.$item->variant_name_snapshot;
                }
                $producer = $this->producerLabel($item) ?? $product?->producer?->name;
                $positions[] = $producer ? sprintf('%s (%s)', $name, $producer) : $name;

                if ($product !== null) {
                    [$qtyDisplay, $unitLabel] = $this->displayQuantity(
                        $product->unit,
                        $product->price_display_unit,
                        (float) $item->quantity,
                    );
                    $quantities[] = trim(number_format($qtyDisplay, 2, '.', '').' '.$unitLabel);
                } else {
                    // Custom line — entered quantity + free-text unit, no conversion.
                    $quantities[] = $item->displayQuantityLabel();
                }

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

        // B2B restaurant deliveries: one row each, id prefixed "R" so packers
        // see the channel at a glance. Only lines that will ship are listed.
        foreach ($this->restaurantOrders as $order) {
            $rows[] = $this->restaurantRow($order);
        }

        usort($rows, fn (array $a, array $b): int => [$a[1], (string) $a[0]] <=> [$b[1], (string) $b[0]]);

        return $rows;
    }

    /**
     * @return array<int, mixed>
     */
    private function restaurantRow(RestaurantOrder $order): array
    {
        $positions = [];
        $quantities = [];
        $unitPrices = [];
        $lineTotals = [];

        foreach ($order->items as $item) {
            if (! $item->isConfirmedLine()) {
                continue; // declined lines never ship
            }

            $product = $item->product;
            $name = $item->displayName();
            if ($item->variant_name_snapshot !== null && $item->variant_name_snapshot !== '') {
                $name .= ' · '.$item->variant_name_snapshot;
            }
            $producer = $this->producerLabel($item) ?? $product?->producer?->name;
            $positions[] = $producer ? sprintf('%s (%s)', $name, $producer) : $name;

            if ($product !== null) {
                [$qtyDisplay, $unitLabel] = $this->displayQuantity(
                    $product->unit,
                    $product->price_display_unit,
                    (float) $item->quantity,
                );
                $quantities[] = trim(number_format($qtyDisplay, 2, '.', '').' '.$unitLabel);

                $unitPriceDisplay = $product->unit !== null
                    ? round(
                        (float) $item->unit_price_snapshot_eur
                            * $product->unit->canonicalUnitsPerDisplay(
                                $product->price_display_unit ?? $product->unit->defaultDisplayUnit()
                            ),
                        2,
                    )
                    : round((float) $item->unit_price_snapshot_eur, 2);
            } else {
                // Custom line — entered quantity + free-text unit, no conversion.
                $quantities[] = $item->displayQuantityLabel();
                $unitPriceDisplay = round((float) $item->unit_price_snapshot_eur, 2);
            }
            $unitPrices[] = number_format($unitPriceDisplay, 2, '.', '');
            $lineTotals[] = number_format((float) $item->line_total_eur, 2, '.', '');
        }

        return [
            'R'.$order->id,
            $order->expected_delivery_date?->format('Y-m-d') ?? '',
            '',
            $order->user?->restaurantProfile?->business_name ?: ($order->user?->name ?? ''),
            (string) __('shop.export.type_business'),
            $order->user?->phone ?? '',
            trim(implode(', ', array_filter([
                $order->delivery_street,
                trim(($order->delivery_postal_code ?? '').' '.($order->delivery_city ?? '')),
            ])), ', '),
            '',
            $order->delivery_notes ?? '',
            implode("\n", $positions),
            implode("\n", $quantities),
            implode("\n", $unitPrices),
            implode("\n", $lineTotals),
            (float) $order->items_total_gross_eur,
            0.0,
            0.0,
            '',
            (float) $order->items_total_gross_eur,
            (string) __('shop.export.payment_invoice'),
            (string) __('restaurant.order_status_'.$order->status->value),
        ];
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

    /**
     * Allocation-based producer names for an item's (product, grade, date):
     * the single farm, or "Farm A/Farm B" when the demand splits. Lazily
     * computed once per workbook from both channels' items.
     */
    private ?array $producerLabelCache = null;

    private function producerLabel(object $item): ?string
    {
        if ($item->product_id === null) {
            return null;
        }
        if ($this->producerLabelCache === null) {
            $this->producerLabelCache = $this->buildProducerLabels();
        }

        return $this->producerLabelCache[$item->product_id.'|'.((int) ($item->variant_id ?? 0))] ?? null;
    }

    /** @return array<string, string> */
    private function buildProducerLabels(): array
    {
        $demand = [];
        $dates = [];
        $fold = function ($order, $item) use (&$demand, &$dates): void {
            if ($item->product_id === null) {
                return;
            }
            $iso = $order->expected_delivery_date?->toDateString();
            if ($iso === null) {
                return;
            }
            $pid = (int) $item->product_id;
            $vkey = (int) ($item->variant_id ?? 0);
            $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + (float) $item->quantity;
            $dates[$iso] = true;
        };
        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $fold($order, $item);
            }
        }
        foreach ($this->restaurantOrders as $order) {
            foreach ($order->items as $item) {
                if (method_exists($item, 'isConfirmedLine') && ! $item->isConfirmedLine()) {
                    continue;
                }
                $fold($order, $item);
            }
        }
        if ($demand === []) {
            return [];
        }

        $allocation = app(ProducerAllocationService::class)
            ->allocate(array_keys($demand), array_keys($dates), $demand);

        $totals = [];
        foreach ($allocation as $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    foreach ($shares as $producerId => $qty) {
                        $totals[$pid][$vkey][$producerId] = ($totals[$pid][$vkey][$producerId] ?? 0.0) + $qty;
                    }
                }
            }
        }
        $ids = [];
        foreach ($totals as $byVkey) {
            foreach ($byVkey as $shares) {
                foreach (array_keys($shares) as $id) {
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
            }
        }
        $names = Producer::query()->with('translations')
            ->whereIn('id', array_keys($ids))->get()
            ->mapWithKeys(fn ($p) => [$p->id => (string) $p->name]);

        $labels = [];
        foreach ($totals as $pid => $byVkey) {
            foreach ($byVkey as $vkey => $shares) {
                $shares = array_filter($shares, fn (float $q): bool => $q > 1e-9);
                if ($shares === []) {
                    continue;
                }
                arsort($shares);
                $parts = [];
                foreach (array_keys($shares) as $producerId) {
                    if ($producerId > 0 && isset($names[$producerId])) {
                        $parts[] = (string) $names[$producerId];
                    }
                }
                if ($parts !== []) {
                    $labels[$pid.'|'.$vkey] = implode('/', $parts);
                }
            }
        }

        return $labels;
    }
}
