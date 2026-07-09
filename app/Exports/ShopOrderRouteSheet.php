<?php

namespace App\Exports;

use App\Enums\ShopOrderStatus;
use App\Exports\Concerns\FormatsShopOrders;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Driver-facing route list: one row per delivery order, sorted by postal
 * code → street so the printout follows roughly the order of the trip.
 * Includes a "payment due at door" column so the driver knows which stops
 * still need cash/card collection. B2B restaurant deliveries appear as
 * regular stops with a B2B marker and zero payment due (invoice payment).
 */
class ShopOrderRouteSheet implements FromArray, WithHeadings, WithTitle
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
        return (string) __('shop.export.sheet_route');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_postal_code'),
            (string) __('shop.export.col_city'),
            (string) __('shop.export.col_street'),
            (string) __('shop.export.col_customer_name'),
            (string) __('shop.export.col_phone'),
            (string) __('shop.export.col_order_id'),
            (string) __('shop.export.col_delivery_date'),
            (string) __('shop.export.col_window'),
            (string) __('shop.export.col_item_count'),
            (string) __('shop.export.col_total_eur'),
            (string) __('shop.export.col_payment_due_eur'),
            (string) __('shop.export.col_notes'),
            (string) __('shop.export.col_customer_type'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            if ($order->fulfillment_mode !== 'delivery') {
                continue;
            }

            // "Due at door" = total still owed when the driver arrives. Online
            // orders are pre-paid (or shouldn't ship); only in_person + still
            // PendingPayment counts toward cash to collect.
            $due = ($order->payment_method === 'in_person'
                && $order->status === ShopOrderStatus::PendingPayment)
                ? (float) $order->total_amount_eur
                : 0.0;

            $rows[] = [
                $order->delivery_postal_code ?? '',
                $order->delivery_city ?? '',
                $order->delivery_street ?? '',
                $order->name ?? '',
                $order->phone ?? '',
                $order->id,
                $order->expected_delivery_date?->format('Y-m-d') ?? '',
                $this->windowLabel($order),
                $order->items->count(),
                (float) $order->total_amount_eur,
                round($due, 2),
                $order->delivery_notes ?? '',
                $this->customerTypeLabel($order->customer_type),
            ];
        }

        // B2B restaurant orders are always deliveries; they pay by invoice,
        // so nothing is due at the door.
        foreach ($this->restaurantOrders as $order) {
            $rows[] = [
                $order->delivery_postal_code ?? '',
                $order->delivery_city ?? '',
                $order->delivery_street ?? '',
                $order->user?->restaurantProfile?->business_name ?: ($order->user?->name ?? ''),
                $order->user?->phone ?? '',
                'R'.$order->id,
                $order->expected_delivery_date?->format('Y-m-d') ?? '',
                '',
                $order->items->filter(fn ($i) => $i->isConfirmedLine())->count(),
                (float) $order->items_total_gross_eur,
                0.0,
                $order->delivery_notes ?? '',
                (string) __('shop.export.type_business'),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a[0], $a[2], (string) $a[5]] <=> [$b[0], $b[2], (string) $b[5]];
        });

        return $rows;
    }
}
