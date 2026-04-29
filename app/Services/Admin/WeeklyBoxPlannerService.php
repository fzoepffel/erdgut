<?php

namespace App\Services\Admin;

use App\Console\Commands\PrepareWeeklyBoxes;
use App\Enums\BoxStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\SubscriberWeeklyDeliverySync;
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
        private readonly BoxCompositionService $compositionService,
        private readonly SubscriberWeeklyDeliverySync $weeklyDeliverySync,
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
     * Planner horizon length from config, safe when {@see config()} is null (e.g. stale `config:cache` missing newer keys).
     */
    public static function configWeeksAhead(): int
    {
        $v = config('admin.box_planner_weeks_ahead');
        if ($v === null || $v === '') {
            return 4;
        }

        return min(max((int) $v, 1), 12);
    }

    /**
     * @return list<Carbon> Consecutive Mondays, same time-of-day as $firstMonday.
     */
    public function horizonWeekStarts(Carbon $firstMonday, ?int $weekCount = null): array
    {
        $n = $weekCount ?? self::configWeeksAhead();

        $weeks = [];
        $d = $firstMonday->copy();
        for ($i = 0; $i < $n; $i++) {
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

    /**
     * Trial orders that should ship in the given delivery week. Match anything
     * that's been paid or scheduled (i.e. ready to ship) AND has a
     * scheduled_for_date that falls inside the Mon-Sun window — that's how
     * trials get pinned to a delivery week in the wizard. Skip orders that
     * already have a non-editable Box (delivered/locked) — they're frozen.
     *
     * @return Collection<int, Order>
     */
    public function eligibleTrialOrdersForWeek(Carbon $weekStart): Collection
    {
        $monday = $weekStart->copy()->startOfDay();
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);

        return Order::query()
            ->where('product_type', OrderProductType::TrialBox->value)
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Scheduled->value,
            ])
            ->whereBetween('scheduled_for_date', [$monday->toDateString(), $sunday->toDateString()])
            ->with(['user'])
            ->orderBy('id')
            ->get();
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
            ->whereNull('order_id')
            ->exists();
    }

    /**
     * Auto-project the first Box + Delivery for a subscription that just
     * activated (token + Active status). Idempotent: if a Box already
     * exists for the user's first eligible week, it's reused. Used by the
     * onboarding wizard's `finalizeSetup` and by admin manual activation
     * paths so the new sub appears in Box-Setup + Lieferungen
     * immediately, without anyone having to re-open the planner.
     *
     * Returns the Box (existing or newly created), or null when the
     * subscription has no expected delivery date (paused indefinitely,
     * cancelled, etc.).
     */
    public function ensureFirstBoxForActivation(Subscription $subscription): ?Box
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return null;
        }
        if (empty($subscription->sumup_token)) {
            // Without a payment mandate the SumUp charge will fail anyway —
            // don't pollute the planner with a box we can't bill.
            return null;
        }

        $deliveryDate = $subscription->firstExpectedDeliveryDate();
        if ($deliveryDate === null) {
            return null;
        }

        $weekStart = $deliveryDate->copy()->startOfWeek(Carbon::MONDAY);

        return $this->ensureEmptyBox($subscription, $weekStart);
    }

    /**
     * Find existing box or create an empty editable box (no line items). Idempotent.
     */
    public function ensureEmptyBox(Subscription $subscription, Carbon $weekStart): Box
    {
        $existing = Box::query()
            ->forUser($subscription->user_id)
            ->forWeek($weekStart->toDateString())
            ->whereNull('order_id')
            ->first();

        if ($existing instanceof Box) {
            return $existing;
        }

        $box = Box::create([
            'user_id' => $subscription->user_id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $this->modificationDeadlineForWeek($weekStart),
            'estimated_value' => 0,
        ]);

        $this->weeklyDeliverySync->syncForUserWeek($subscription->user_id, $weekStart);

        return $box;
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

        $this->weeklyDeliverySync->syncForUserWeek($subscription->user_id, $weekStart);

        return true;
    }
}
