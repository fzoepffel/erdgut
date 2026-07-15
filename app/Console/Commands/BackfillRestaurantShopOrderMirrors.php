<?php

namespace App\Console\Commands;

use App\Enums\RestaurantOrderStatus;
use App\Models\RestaurantOrder;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Console\Command;

/**
 * One-off backfill: create the mirror ShopOrder for every firm RestaurantOrder
 * (Confirmed / Fulfilled) that predates the mirror rollout. Idempotent —
 * ensureShopOrderMirror() returns the existing mirror when one already exists,
 * so the command is safe to re-run.
 */
class BackfillRestaurantShopOrderMirrors extends Command
{
    protected $signature = 'restaurant:backfill-shop-order-mirrors {--dry-run : Report the count without writing}';

    protected $description = 'Create a mirror ShopOrder for every firm RestaurantOrder that does not yet have one.';

    public function handle(RestaurantOrderService $service): int
    {
        $query = RestaurantOrder::query()
            ->whereIn('status', [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value])
            ->whereDoesntHave('mirroredShopOrder');

        $total = $query->count();
        $this->info("Firm RestaurantOrders missing a mirror: {$total}");

        if ($total === 0 || $this->option('dry-run')) {
            return self::SUCCESS;
        }

        $created = 0;
        $query->cursor()->each(function (RestaurantOrder $order) use ($service, &$created): void {
            if ($service->ensureShopOrderMirror($order) !== null) {
                $created++;
            }
        });

        $this->info("Mirrors created: {$created}");

        return self::SUCCESS;
    }
}
