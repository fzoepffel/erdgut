<?php

namespace App\Console\Commands;

use App\Services\Admin\RecurringStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Extend the materialization window for recurring weekly-stock schedules.
 *
 * Indefinite schedules ("10 kg/week forever") only materialize 26 weeks
 * ahead at creation time. This command (scheduled weekly on Monday at
 * 03:00) tops the window up to 30 weeks ahead so admins always have at
 * least a 4-week buffer of materialized rows visible in the weekly
 * catalog UI.
 *
 * Idempotent — safe to run as often as you like.
 */
class MaterializeWeeklyStockSchedules extends Command
{
    protected $signature = 'stock:materialize-schedules';

    protected $description = 'Materialize ProductWeekStock rows from active recurring schedules up to the cron horizon.';

    public function handle(RecurringStockService $service): int
    {
        $horizon = Carbon::now()->copy()->addWeeks(RecurringStockService::CRON_MATERIALIZE_WEEKS_AHEAD);
        $written = $service->materializeWindow($horizon);

        $this->info("Materialized {$written} ProductWeekStock rows through {$horizon->startOfWeek(Carbon::MONDAY)->toDateString()}.");

        return self::SUCCESS;
    }
}
