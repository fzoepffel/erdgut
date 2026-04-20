<?php

namespace App\Console\Commands;

use App\Models\Box;
use App\Services\SubscriberWeeklyDeliverySync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncDeliveriesFromBoxes extends Command
{
    protected $signature = 'deliveries:sync-from-boxes
                            {--from= : Lower bound for box week_start_date (Y-m-d). Default: start of current ISO week.}';

    protected $description = 'Ensure canonical Delivery rows exist for future weekly boxes (Wednesday ship day, same rules as demo seed).';

    public function handle(SubscriberWeeklyDeliverySync $sync): int
    {
        $from = $this->option('from') !== null && $this->option('from') !== ''
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : Carbon::now()->startOfWeek()->startOfDay();

        $pairs = Box::query()
            ->whereDate('week_start_date', '>=', $from->toDateString())
            ->select(['user_id', 'week_start_date'])
            ->distinct()
            ->orderBy('week_start_date')
            ->orderBy('user_id')
            ->get();

        foreach ($pairs as $row) {
            $sync->syncForUserWeek((int) $row->user_id, Carbon::parse($row->week_start_date));
        }

        $this->info("Synced deliveries for {$pairs->count()} user-week pair(s) with week_start_date >= {$from->toDateString()}.");

        return self::SUCCESS;
    }
}
