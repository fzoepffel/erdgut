<?php

namespace App\Exports;

use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Five-sheet workbook for an admin-filtered set of {@see ShopOrder}s.
 *
 *  1. Übersicht — KPI dashboard + echo of the active filter.
 *  2. Bestellungen — one row per order, positions stacked inside cells.
 *  3. Hof-Summen — aggregated per producer × product (procurement).
 *  4. Tour — delivery orders sorted by PLZ → street (driver).
 *  5. Abholungen — pickup orders grouped by location.
 *
 * Orders must be eager-loaded with `items.product.producer.translations`
 * so the sheets don't trigger N+1 lookups per row.
 */
class ShopOrdersExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, ShopOrder>  $orders
     * @param  array{status: string, delivery_from: string, delivery_to: string}  $filter
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly array $filter,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new ShopOrderSummarySheet($this->orders, $this->filter),
            new ShopOrderPicklistSheet($this->orders),
            new ShopOrderFarmTotalsSheet($this->orders),
            new ShopOrderRouteSheet($this->orders),
            new ShopOrderPickupsSheet($this->orders),
        ];
    }
}
