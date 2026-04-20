<?php

namespace App\Console\Commands;

use App\Services\DeliveriesFromBoxesSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncDeliveriesFromBoxes extends Command
{
    protected $signature = 'deliveries:sync-from-boxes
                            {--from= : Lower bound for box week_start_date (Y-m-d). Default: start of current ISO week.}';

    protected $description = 'Ensure canonical Delivery rows exist for future weekly boxes (Wednesday ship day, same rules as demo seed).';

    public function handle(DeliveriesFromBoxesSync $deliveriesFromBoxesSync): int
    {
        $fromCarbon = $this->option('from') !== null && $this->option('from') !== ''
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : Carbon::now()->startOfWeek()->startOfDay();

        $count = $deliveriesFromBoxesSync->run($fromCarbon);

        $this->info("Synced deliveries for {$count} user-week pair(s) with week_start_date >= {$fromCarbon->toDateString()}.");

        return self::SUCCESS;
    }
}
