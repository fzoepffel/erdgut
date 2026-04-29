<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Http\Requests\Admin\AdminDeliveryStoreRequest;
use App\Models\Delivery;
use Illuminate\Console\Command;

/**
 * One-shot cleanup for the legacy orphan-delivery problem.
 *
 * An "orphan" canonical delivery has both `box_id` and `order_id` null AND
 * is not a weekly override AND its status is still `scheduled` — so it
 * has nothing physical to ship. The validation in
 * {@see AdminDeliveryStoreRequest} now refuses to
 * create new orphans, but historic data (test seeds, old admin clicks)
 * can still hold them.
 *
 * Running this drops every such row. Use `--dry-run` to preview first.
 */
class CleanupOrphanDeliveries extends Command
{
    protected $signature = 'deliveries:cleanup-orphans
                            {--dry-run : Print the rows that would be deleted without touching the DB.}';

    protected $description = 'Delete scheduled canonical deliveries with no Box and no Order behind them.';

    public function handle(): int
    {
        $orphans = Delivery::query()
            ->where('status', DeliveryStatus::Scheduled)
            ->whereNull('box_id')
            ->whereNull('order_id')
            ->where('is_weekly_override', false)
            ->with('user:id,email')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan deliveries found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($orphans as $delivery) {
            $rows[] = [
                'id' => $delivery->id,
                'user' => $delivery->user?->email ?? '—',
                'scheduled_for_date' => $delivery->scheduled_for_date?->toDateString() ?? '—',
            ];
        }
        $this->table(['id', 'user', 'scheduled_for_date'], $rows);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no rows deleted. Re-run without --dry-run to delete '.$orphans->count().' delivery row(s).');

            return self::SUCCESS;
        }

        $deleted = Delivery::query()
            ->whereIn('id', $orphans->pluck('id'))
            ->delete();

        $this->info("Deleted {$deleted} orphan delivery row(s).");

        return self::SUCCESS;
    }
}
