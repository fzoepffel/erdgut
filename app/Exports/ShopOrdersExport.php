<?php

namespace App\Exports;

use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Two-sheet workbook for an admin-filtered set of {@see ShopOrder}s.
 *
 *  - Deliveries: one row per order line so ops can pack each customer.
 *  - Farm totals: aggregated quantities per producer/product so procurement
 *    knows exactly what to source.
 *
 * Orders must already be loaded with `items.product.producer.translations` so
 * the sheets don't trigger N+1 lookups per row.
 */
class ShopOrdersExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, ShopOrder>  $orders
     */
    public function __construct(private readonly Collection $orders) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new ShopOrderDeliveriesSheet($this->orders),
            new ShopOrderFarmTotalsSheet($this->orders),
        ];
    }
}
