<?php

namespace App\Console\Commands;

use App\Enums\BoxStatus;
use App\Models\Box;
use Illuminate\Console\Command;

class LockExpiredBoxes extends Command
{
    protected $signature = 'boxes:lock
                            {--dry-run : List eligible boxes without locking them}';

    protected $description = 'Lock boxes whose modification deadline has passed';

    public function handle(): int
    {
        $boxes = Box::query()
            ->where('status', BoxStatus::Editable)
            ->where('modification_deadline_at', '<=', now())
            ->get();

        if ($boxes->isEmpty()) {
            $this->info('No boxes to lock.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$boxes->count()} box(es) would be locked.");
            $this->table(
                ['Box ID', 'User', 'Week Start', 'Deadline'],
                $boxes->map(fn (Box $b) => [
                    $b->id,
                    $b->user?->email ?? '—',
                    $b->week_start_date?->toDateString() ?? '—',
                    $b->modification_deadline_at?->toDateTimeString() ?? '—',
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $locked = 0;

        foreach ($boxes as $box) {
            $box->lock();
            $locked++;
        }

        $this->info("Locked {$locked} box(es).");

        return self::SUCCESS;
    }
}
