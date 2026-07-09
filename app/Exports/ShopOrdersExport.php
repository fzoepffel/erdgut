<?php

namespace App\Exports;

use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Five-sheet workbook for an admin-filtered set of {@see ShopOrder}s plus the
 * matching B2B {@see RestaurantOrder}s, so demand planning sees
 * both channels.
 *
 *  1. Übersicht — KPI dashboard + echo of the active filter (separate B2B block).
 *  2. Bestellungen — one row per order (B2C and B2B), positions stacked inside cells.
 *  3. Hof-Summen — aggregated per producer × product across both channels (procurement).
 *  4. Tour — delivery orders sorted by PLZ → street (driver); B2B stops included.
 *  5. Abholungen — pickup orders grouped by location (B2C only; B2B is always delivery).
 *
 * Orders must be eager-loaded with `items.product.producer.translations`
 * (restaurant orders additionally with `user.restaurantProfile`) so the
 * sheets don't trigger N+1 lookups per row.
 */
class ShopOrdersExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, ShopOrder>  $orders
     * @param  array{status: string, delivery_from: string, delivery_to: string}  $filter
     * @param  Collection<int, RestaurantOrder>  $restaurantOrders
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly array $filter,
        private readonly Collection $restaurantOrders = new Collection,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new ShopOrderSummarySheet($this->orders, $this->filter, $this->restaurantOrders),
            new ShopOrderPicklistSheet($this->orders, $this->restaurantOrders),
            new ShopOrderFarmTotalsSheet($this->orders, $this->restaurantOrders),
            new ShopOrderRouteSheet($this->orders, $this->restaurantOrders),
            new ShopOrderPickupsSheet($this->orders),
        ];
    }
}
