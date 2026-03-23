<?php

namespace App\Console\Commands;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Subscription;
use App\Support\BoxCompositionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PrepareWeeklyBoxes extends Command
{
    protected $signature = 'boxes:prepare
                            {--week= : Target week start date (Y-m-d), defaults to next Monday}
                            {--dry-run : List eligible subscriptions without creating boxes}';

    protected $description = 'Create upcoming week boxes for all eligible active subscribers';

    public function __construct(
        private readonly BoxCompositionService $compositionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $weekStart = $this->resolveWeekStart();
        $subscriptions = $this->getEligibleSubscriptions($weekStart);

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

        $deadline = $weekStart->copy()->subDays(2);
        $created = 0;
        $skipped = 0;

        foreach ($subscriptions as $subscription) {
            $exists = Box::query()
                ->forUser($subscription->user_id)
                ->forWeek($weekStart->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $box = Box::create([
                'user_id' => $subscription->user_id,
                'week_start_date' => $weekStart->toDateString(),
                'status' => BoxStatus::Editable,
                'modification_deadline_at' => $deadline,
                'estimated_value' => 0,
            ]);

            $this->compositionService->compose($box, (string) $subscription->box_size);

            $created++;
        }

        $this->info("Created {$created} box(es) for {$weekStart->toDateString()}.".($skipped > 0 ? " Skipped {$skipped} (already exist)." : ''));

        return self::SUCCESS;
    }

    private function resolveWeekStart(): Carbon
    {
        $option = $this->option('week');

        if ($option !== null) {
            return Carbon::parse($option)->startOfDay();
        }

        return Carbon::today()->next(Carbon::MONDAY);
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function getEligibleSubscriptions(Carbon $weekStart): Collection
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('user')
            ->get()
            ->filter(fn (Subscription $s) => ! $s->isWithinPauseWindow($weekStart))
            ->filter(fn (Subscription $s) => $this->matchesPlanCadence($s, $weekStart))
            ->values();
    }

    private function matchesPlanCadence(Subscription $subscription, Carbon $weekStart): bool
    {
        if ($subscription->plan === SubscriptionPlan::Weekly) {
            return true;
        }

        if ($subscription->plan === SubscriptionPlan::Biweekly) {
            return (int) $weekStart->weekOfYear % 2 === 0;
        }

        return true;
    }
}
