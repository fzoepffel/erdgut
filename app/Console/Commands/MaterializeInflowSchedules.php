<?php

namespace App\Console\Commands;

use App\Services\Admin\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Extend the materialization window for recurring inflow schedules.
 *
 * Indefinite schedules ("10 kg every Wednesday, forever") only
 * materialize {@see InventoryService::MATERIALIZE_WEEKS_AHEAD} weeks
 * ahead at creation. This command (scheduled weekly Mondays 03:00)
 * tops the window up to {@see InventoryService::CRON_MATERIALIZE_WEEKS_AHEAD}
 * so admins always have a few weeks of slack in the Inventar matrix.
 *
 * Idempotent — safe to run as often as you like.
 */
class MaterializeInflowSchedules extends Command
{
    protected $signature = 'stock:materialize-inflow-schedules';

    protected $description = 'Materialize ProductStockInflow rows from active inflow schedules up to the cron horizon.';

    public function handle(InventoryService $service): int
    {
        $horizon = Carbon::now()->copy()->addWeeks(InventoryService::CRON_MATERIALIZE_WEEKS_AHEAD);
        $written = $service->materializeWindow($horizon);

        $this->info("Materialized {$written} ProductStockInflow rows through {$horizon->toDateString()}.");

        return self::SUCCESS;
    }
}
