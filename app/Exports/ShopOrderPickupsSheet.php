<?php

namespace App\Exports;

use App\Enums\ShopOrderStatus;
use App\Exports\Concerns\FormatsShopOrders;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Pickup-point staff list: one row per pickup order, sorted by
 * pickup_location → date so each location can read its own block.
 */
class ShopOrderPickupsSheet implements FromArray, WithHeadings, WithTitle
{
    use FormatsShopOrders;

    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_pickups');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_pickup_location'),
            (string) __('shop.export.col_delivery_date'),
            (string) __('shop.export.col_window'),
            (string) __('shop.export.col_customer_name'),
            (string) __('shop.export.col_phone'),
            (string) __('shop.export.col_order_id'),
            (string) __('shop.export.col_item_count'),
            (string) __('shop.export.col_total_eur'),
            (string) __('shop.export.col_payment_status'),
            (string) __('shop.export.col_notes'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            if ($order->fulfillment_mode !== 'pickup') {
                continue;
            }

            $paymentStatus = ($order->payment_method === 'in_person'
                && $order->status === ShopOrderStatus::PendingPayment)
                ? (string) __('shop.export.payment_status_pay_at_pickup')
                : $this->statusLabel($order->status);

            $rows[] = [
                $this->pickupLocationLabel($order->pickup_location),
                $order->expected_delivery_date?->format('Y-m-d') ?? '',
                $this->windowLabel($order),
                $order->name ?? '',
                $order->phone ?? '',
                $order->id,
                $order->items->count(),
                (float) $order->total_amount_eur,
                $paymentStatus,
                $order->delivery_notes ?? '',
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a[0], $a[1], $a[5]] <=> [$b[0], $b[1], $b[5]];
        });

        return $rows;
    }
}
