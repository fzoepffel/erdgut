<?php

namespace App\Console\Commands;

use App\Models\DashboardClickEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retention sweep for dashboard click instrumentation.
 *
 * Click events accumulate quickly (one row per dashboard click) and we only
 * need a rolling window to inform the redesign decision. 90 days is plenty
 * to spot patterns across multiple billing cycles. Wired into the daily
 * scheduler.
 */
class PurgeDashboardClickEvents extends Command
{
    protected $signature = 'dashboard:purge-click-events {--days=90}';

    protected $description = 'Delete dashboard_click_events rows older than the retention window (default 90 days).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $deleted = DashboardClickEvent::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} dashboard click event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
