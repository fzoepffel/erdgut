<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Console\Command;

class PrepareWeeklyBoxes extends Command
{
    protected $signature = 'boxes:prepare
                            {--week= : Target week start date (Y-m-d), defaults to next Monday}
                            {--dry-run : List eligible subscriptions without creating boxes}';

    protected $description = 'Create upcoming week boxes for all eligible active subscribers';

    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $weekStart = $this->weeklyBoxPlanner->resolveWeekStartFromOption($this->option('week'));
        $subscriptions = $this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($weekStart);

        if ($subscriptions->isEmpty()) {
            $this->info('No eligible subscriptions for '.$weekStart->toDateString().'.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$subscriptions->count()} box(es) would be created for {$weekStart->toDateString()}.");
            $this->table(
                ['Subscription', 'User', 'Plan', 'Box size'],
                $subscriptions->map(fn (Subscription $s) => [
                    $s->id,
                    $s->user?->email ?? '—',
                    $s->plan->value,
                    $s->box_size,
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($subscriptions as $subscription) {
            if ($this->weeklyBoxPlanner->createBoxWithPresetIfMissing($subscription, $weekStart)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->info("Created {$created} box(es) for {$weekStart->toDateString()}.".($skipped > 0 ? " Skipped {$skipped} (already exist)." : ''));

        return self::SUCCESS;
    }
}
