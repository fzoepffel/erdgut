<?php

namespace App\Services\Admin;

use App\Console\Commands\PrepareWeeklyBoxes;
use App\Enums\BoxStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Subscription;
use App\Support\BoxCompositionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Eligibility and box provisioning for weekly boxes, shared by {@see PrepareWeeklyBoxes}
 * and the admin box planner (ERD-190). See docs/ADMIN-BOX-PLANNER.md.
 */
final class WeeklyBoxPlannerService
{
    public function __construct(
        private readonly BoxCompositionService $compositionService
    ) {}

    /**
     * Default first column for the planner and for `boxes:prepare` without `--week`.
     */
    public static function defaultUpcomingWeekMonday(?Carbon $today = null): Carbon
    {
        return ($today ?? Carbon::today())->copy()->next(Carbon::MONDAY)->startOfDay();
    }

    public function resolveWeekStartFromOption(?string $weekOption, ?Carbon $today = null): Carbon
    {
        if ($weekOption !== null && $weekOption !== '') {
            return Carbon::parse($weekOption)->startOfDay();
        }

        return self::defaultUpcomingWeekMonday($today);
    }

    /**
     * @return list<Carbon> Consecutive Mondays, same time-of-day as $firstMonday.
     */
    public function horizonWeekStarts(Carbon $firstMonday, int $weekCount): array
    {
        $weeks = [];
        $d = $firstMonday->copy();
        for ($i = 0; $i < $weekCount; $i++) {
            $weeks[] = $d->copy();
            $d->addWeek();
        }

        return $weeks;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function eligibleSubscriptionsForWeek(Carbon $weekStart): Collection
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('user')
            ->get()
            ->filter(fn (Subscription $s) => ! $s->isWithinPauseWindow($weekStart))
            ->filter(fn (Subscription $s) => $this->matchesPlanCadence($s, $weekStart))
            ->values();
    }

    public function matchesPlanCadence(Subscription $subscription, Carbon $weekStart): bool
    {
        if ($subscription->plan === SubscriptionPlan::Weekly) {
            return true;
        }

        if ($subscription->plan === SubscriptionPlan::Biweekly) {
            return (int) $weekStart->weekOfYear % 2 === 0;
        }

        return true;
    }

    public function isEligibleForWeek(Subscription $subscription, Carbon $weekStart): bool
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return false;
        }

        if ($subscription->isWithinPauseWindow($weekStart)) {
            return false;
        }

        return $this->matchesPlanCadence($subscription, $weekStart);
    }

    public function modificationDeadlineForWeek(Carbon $weekStart): Carbon
    {
        return $weekStart->copy()->subDays(2);
    }

    public function boxExistsForUserWeek(int $userId, Carbon $weekStart): bool
    {
        return Box::query()
            ->forUser($userId)
            ->forWeek($weekStart->toDateString())
            ->exists();
    }

    /**
     * Find existing box or create an empty editable box (no line items). Idempotent.
     */
    public function ensureEmptyBox(Subscription $subscription, Carbon $weekStart): Box
    {
        $existing = Box::query()
            ->forUser($subscription->user_id)
            ->forWeek($weekStart->toDateString())
            ->first();

        if ($existing instanceof Box) {
            return $existing;
        }

        return Box::create([
            'user_id' => $subscription->user_id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $this->modificationDeadlineForWeek($weekStart),
            'estimated_value' => 0,
        ]);
    }

    /**
     * Create box and run preset composition. Returns false if a box already exists for that user/week.
     */
    public function createBoxWithPresetIfMissing(Subscription $subscription, Carbon $weekStart): bool
    {
        if ($this->boxExistsForUserWeek($subscription->user_id, $weekStart)) {
            return false;
        }

        $box = Box::create([
            'user_id' => $subscription->user_id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $this->modificationDeadlineForWeek($weekStart),
            'estimated_value' => 0,
        ]);

        $this->compositionService->compose($box, (string) $subscription->box_size);

        return true;
    }
}
