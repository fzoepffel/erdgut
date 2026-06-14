<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsShopOrders;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One row per order — the office-facing "who gets what, where, when, paid?"
 * summary. Sorted by delivery date → order id so a printed copy follows the
 * same physical sequence as the picklist.
 */
class ShopOrderCustomerSummarySheet implements FromArray, WithHeadings, WithTitle
{
    use FormatsShopOrders;

    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_customers');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_order_id'),
            (string) __('shop.export.col_status'),
            (string) __('shop.export.col_source'),
            (string) __('shop.export.col_customer_name'),
            (string) __('shop.export.col_customer_type'),
            (string) __('shop.export.col_phone'),
            (string) __('shop.export.col_email'),
            (string) __('shop.export.col_fulfillment'),
            (string) __('shop.export.col_delivery_date'),
            (string) __('shop.export.col_window'),
            (string) __('shop.export.col_address'),
            (string) __('shop.export.col_pickup_location'),
            (string) __('shop.export.col_notes'),
            (string) __('shop.export.col_item_count'),
            (string) __('shop.export.col_items_total_eur'),
            (string) __('shop.export.col_delivery_fee_eur'),
            (string) __('shop.export.col_discount_eur'),
            (string) __('shop.export.col_coupon'),
            (string) __('shop.export.col_total_eur'),
            (string) __('shop.export.col_payment_method'),
            (string) __('shop.export.col_paid_at'),
            (string) __('shop.export.col_fulfilled_at'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            $rows[] = [
                $order->id,
                $this->statusLabel($order->status),
                $this->orderSourceLabel($order->order_source),
                $order->name ?? '',
                $this->customerTypeLabel($order->customer_type),
                $order->phone ?? '',
                $order->email ?? '',
                $this->fulfillmentLabel($order->fulfillment_mode),
                $order->expected_delivery_date?->format('Y-m-d') ?? '',
                $this->windowLabel($order),
                $this->addressLine($order),
                $this->pickupLocationLabel($order->pickup_location),
                $order->delivery_notes ?? '',
                $order->items->count(),
                (float) $order->items_total_eur,
                (float) $order->delivery_fee_eur,
                (float) $order->discount_eur,
                $order->coupon_code ?? '',
                (float) $order->total_amount_eur,
                $this->paymentMethodLabel($order->payment_method),
                $order->paid_at?->format('Y-m-d H:i') ?? '',
                $order->fulfilled_at?->format('Y-m-d H:i') ?? '',
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a[8], $a[0]] <=> [$b[8], $b[0]];
        });

        return $rows;
    }
}
