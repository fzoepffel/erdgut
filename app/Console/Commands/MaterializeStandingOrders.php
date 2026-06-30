<?php

namespace App\Console\Commands;

use App\Services\Restaurant\RestaurantStandingOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generate auto-confirmed restaurant orders from active standing orders for the
 * upcoming lead window. Idempotent — safe to run daily.
 */
class MaterializeStandingOrders extends Command
{
    protected $signature = 'restaurant-orders:materialize-standing';

    protected $description = 'Generate upcoming recurring restaurant orders from active standing orders.';

    public function handle(RestaurantStandingOrderService $service): int
    {
        $written = $service->materializeDue(Carbon::now());
        $this->info("Materialized {$written} recurring restaurant order(s).");

        return self::SUCCESS;
    }
}
