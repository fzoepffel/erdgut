<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Legacy shim — the materializer was renamed in Phase 2 of the
 * daily-inventory rebuild. Production cron servers may still trigger
 * the old signature during the deploy window; this shim dispatches to
 * the new command so no inflow rows are missed during the rollover.
 *
 * Deleted in Phase 5 once the prod cron config has been updated.
 */
class MaterializeWeeklyStockSchedules extends Command
{
    protected $signature = 'stock:materialize-schedules';

    protected $description = '[Deprecated] Forwards to stock:materialize-inflow-schedules.';

    public function handle(): int
    {
        $this->warn('stock:materialize-schedules is deprecated; forwarding to stock:materialize-inflow-schedules.');

        return $this->call('stock:materialize-inflow-schedules');
    }
}
