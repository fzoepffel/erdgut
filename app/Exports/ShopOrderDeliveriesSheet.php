<?php

namespace App\Exports;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Models\ShopOrder;
use App\Support\PickupLocations;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Per-line export so ops can pack each customer. Quantities are converted
 * from the canonical storage unit (g, ml, …) to the product's chosen
 * display unit (kg, L, …) so Excel totals read the way the catalog does.
 */
class ShopOrderDeliveriesSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_deliveries');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_order_id'),
            (string) __('shop.export.col_delivery_date'),
            (string) __('shop.export.col_fulfillment'),
            (string) __('shop.export.col_customer_name'),
            (string) __('shop.export.col_customer_type'),
            (string) __('shop.export.col_email'),
            (string) __('shop.export.col_phone'),
            (string) __('shop.export.col_address'),
            (string) __('shop.export.col_pickup_location'),
            (string) __('shop.export.col_product'),
            (string) __('shop.export.col_producer'),
            (string) __('shop.export.col_quantity'),
            (string) __('shop.export.col_unit'),
            (string) __('shop.export.col_line_total_eur'),
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
                [$qtyDisplay, $unitLabel] = self::displayQuantity(
                    $product?->unit,
                    $product?->price_display_unit,
                    (float) $item->quantity,
                );

                $rows[] = [
                    $order->id,
                    $order->expected_delivery_date?->format('Y-m-d') ?? '',
                    self::fulfillmentLabel($order->fulfillment_mode),
                    $order->name ?? '',
                    self::customerTypeLabel($order->customer_type),
                    $order->email ?? '',
                    $order->phone ?? '',
                    self::addressLine($order),
                    self::pickupLocationLabel($order->pickup_location),
                    $product?->name ?? (string) __('shop.admin.deleted_product'),
                    $product?->producer?->name ?? '',
                    $qtyDisplay,
                    $unitLabel,
                    (float) $item->line_total_eur,
                ];
            }
        }

        return $rows;
    }

    /**
     * Convert a canonical quantity (g/ml/piece/bunch) into the product's
     * preferred display unit (kg/L/g/ml/piece/bunch). Returns
     * `[numericQty, localizedSymbol]` so Menge stays sortable in Excel.
     *
     * @return array{0: float, 1: string}
     */
    private static function displayQuantity(?ProductUnit $unit, ?string $priceDisplayUnit, float $canonical): array
    {
        if (! $unit instanceof ProductUnit) {
            return [$canonical, ''];
        }

        $display = $priceDisplayUnit ?? $unit->defaultDisplayUnit();
        $factor = $unit->canonicalUnitsPerDisplay($display);
        $qty = $factor > 0 ? $canonical / $factor : $canonical;

        return [round($qty, 4), ProductUnit::displaySymbol($display)];
    }

    private static function customerTypeLabel(?CustomerType $type): string
    {
        return match ($type) {
            CustomerType::Business => (string) __('shop.export.type_business'),
            CustomerType::Private => (string) __('shop.export.type_private'),
            default => '',
        };
    }

    private static function fulfillmentLabel(?string $mode): string
    {
        return $mode === 'delivery'
            ? (string) __('shop.fulfillment_delivery')
            : (string) __('shop.fulfillment_pickup');
    }

    private static function addressLine(ShopOrder $order): string
    {
        if ($order->fulfillment_mode !== 'delivery') {
            return '';
        }

        return trim(implode(', ', array_filter([
            $order->delivery_street,
            trim(($order->delivery_postal_code ?? '').' '.($order->delivery_city ?? '')),
        ])), ', ');
    }

    private static function pickupLocationLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        $pickup = PickupLocations::find($key);
        if ($pickup === null) {
            return $key;
        }

        return trim(($pickup['name'] ?? $key).
            (isset($pickup['street']) ? ' — '.$pickup['street'] : ''));
    }
}
