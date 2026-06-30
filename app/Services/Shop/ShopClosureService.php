<?php

namespace App\Services\Shop;

use App\Models\ShopClosedDay;
use App\Models\ShopClosureRule;
use App\Models\ShopForcedOpenDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Operational closures for the public shop delivery-date picker.
 *
 * A day's open/closed state is *derived* on read from three inputs, in
 * precedence order (manual always wins over a rule):
 *   1. past/today                         → closed (storefront horizon)
 *   2. a manual ShopClosedDay row         → closed
 *   3. a manual ShopForcedOpenDay row     → open (overrides a rule)
 *   4. an active recurring rule whose cutoff has passed → closed
 *   5. otherwise                          → open
 *
 * Recurring rules ({@see ShopClosureRule}) auto-close a delivery weekday
 * `lead_days` before the delivery date at `cutoff_time`. Because closures are
 * computed live, the rule "fires" the instant now >= cutoff on the next
 * request — no scheduled job materializes rows, so there is nothing to drift
 * if the (unreliable) cron misses a beat.
 */
class ShopClosureService
{
    /** @var Collection<int, ShopClosureRule>|null Active rules keyed by weekday, memoised per request. */
    private ?Collection $rulesByWeekday = null;

    /**
     * First day closures can be managed — mirrors the public shop (no same-day delivery).
     */
    public function earliestManageableDate(): Carbon
    {
        return Carbon::today()->addDay()->startOfDay();
    }

    public function isManageableDate(Carbon $date): bool
    {
        return $date->copy()->startOfDay()->gte($this->earliestManageableDate());
    }

    public function purgeExpired(): void
    {
        $cutoff = $this->earliestManageableDate()->toDateString();

        ShopClosedDay::query()->whereDate('closed_on', '<', $cutoff)->delete();
        ShopForcedOpenDay::query()->whereDate('forced_open_on', '<', $cutoff)->delete();
    }

    /**
     * Active recurring rules keyed by Carbon dayOfWeek (0=Sun..6=Sat).
     *
     * @return Collection<int, ShopClosureRule>
     */
    public function activeRulesByWeekday(): Collection
    {
        return $this->rulesByWeekday ??= ShopClosureRule::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('weekday');
    }

    public function ruleForWeekday(int $weekday): ?ShopClosureRule
    {
        return $this->activeRulesByWeekday()->get($weekday);
    }

    /** Forget the memoised rules (call after a rule mutation within the same request). */
    public function flushRuleCache(): void
    {
        $this->rulesByWeekday = null;
    }

    /**
     * The moment a delivery date governed by $rule flips to closed.
     */
    public function cutoffFor(Carbon $date, ShopClosureRule $rule): Carbon
    {
        return $date->copy()->startOfDay()
            ->subDays($rule->lead_days)
            ->setTimeFromTimeString((string) $rule->cutoff_time);
    }

    /**
     * Whether an active rule would close $date at the current moment.
     */
    public function ruleClosesNow(Carbon $date): bool
    {
        $rule = $this->ruleForWeekday((int) $date->copy()->dayOfWeek);

        return $rule !== null && Carbon::now()->gte($this->cutoffFor($date, $rule));
    }

    public function isClosed(Carbon $date): bool
    {
        $date = $date->copy()->startOfDay();

        if ($date->lt($this->earliestManageableDate())) {
            return true;
        }

        $iso = $date->toDateString();

        if (ShopClosedDay::query()->whereDate('closed_on', $iso)->exists()) {
            return true; // manual close wins
        }

        if (ShopForcedOpenDay::query()->whereDate('forced_open_on', $iso)->exists()) {
            return false; // manual open beats a rule
        }

        return $this->ruleClosesNow($date);
    }

    /**
     * Toggle a day's *effective* state. Returns the new closed state. Manual
     * overrides (force-closed / force-open rows) always win over a rule.
     */
    public function toggleDay(Carbon $date, ?int $createdByUserId = null): bool
    {
        $date = $date->copy()->startOfDay();

        if (! $this->isManageableDate($date)) {
            throw new \InvalidArgumentException('Closure date must be tomorrow or later.');
        }

        $iso = $date->toDateString();

        if ($this->isClosed($date)) {
            // → open: drop a manual close, and pin force-open only if a rule
            // would otherwise re-close it right now (so untouched future
            // rule-days can still fall back to the rule naturally).
            ShopClosedDay::query()->whereDate('closed_on', $iso)->delete();

            if ($this->ruleClosesNow($date)) {
                ShopForcedOpenDay::query()->firstOrCreate(
                    ['forced_open_on' => $iso],
                    ['created_by_user_id' => $createdByUserId],
                );
            } else {
                ShopForcedOpenDay::query()->whereDate('forced_open_on', $iso)->delete();
            }

            return false;
        }

        // → closed: drop any force-open and record a manual close.
        ShopForcedOpenDay::query()->whereDate('forced_open_on', $iso)->delete();
        ShopClosedDay::query()->firstOrCreate(
            ['closed_on' => $iso],
            ['created_by_user_id' => $createdByUserId],
        );

        return true;
    }

    /**
     * Drop any manual override on a day so it follows its rule again.
     */
    public function clearOverride(Carbon $date): void
    {
        $iso = $date->copy()->startOfDay()->toDateString();

        ShopClosedDay::query()->whereDate('closed_on', $iso)->delete();
        ShopForcedOpenDay::query()->whereDate('forced_open_on', $iso)->delete();
    }

    /**
     * @return list<string> ISO dates effectively closed within [from, to] inclusive.
     */
    public function effectiveClosedIsoDatesBetween(Carbon $from, Carbon $to): array
    {
        return array_keys(array_filter(
            $this->statesForRange($from, $to),
            fn (array $state): bool => $state['closed'],
        ));
    }

    /**
     * Per-date state for a range, computed in memory from a single preload of
     * manual overrides + active rules. Used by the admin calendar and (via
     * {@see effectiveClosedIsoDatesBetween}) the storefront date picker.
     *
     * @return array<string, array{state: string, closed: bool, has_rule: bool, has_override: bool, cutoff_label: ?string}>
     */
    public function statesForRange(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $closedSet = array_fill_keys(
            ShopClosedDay::query()
                ->whereDate('closed_on', '>=', $from->toDateString())
                ->whereDate('closed_on', '<=', $to->toDateString())
                ->pluck('closed_on')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all(),
            true,
        );
        $forcedOpenSet = array_fill_keys(
            ShopForcedOpenDay::query()
                ->whereDate('forced_open_on', '>=', $from->toDateString())
                ->whereDate('forced_open_on', '<=', $to->toDateString())
                ->pluck('forced_open_on')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all(),
            true,
        );
        $rules = $this->activeRulesByWeekday();
        $earliest = $this->earliestManageableDate();
        $now = Carbon::now();

        $out = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $iso = $cursor->toDateString();
            $rule = $rules->get((int) $cursor->dayOfWeek);
            $hasRule = $rule !== null;
            $manualClosed = isset($closedSet[$iso]);
            $forcedOpen = isset($forcedOpenSet[$iso]);
            $cutoffLabel = null;

            if ($cursor->lt($earliest)) {
                // Past/today: not bookable; left to the caller to mark disabled.
                $state = 'open';
                $closed = true;
            } elseif ($manualClosed) {
                $state = 'manual_closed';
                $closed = true;
            } elseif ($forcedOpen) {
                $state = 'forced_open';
                $closed = false;
            } elseif ($hasRule) {
                $cutoff = $this->cutoffFor($cursor, $rule);
                if ($now->gte($cutoff)) {
                    $state = 'rule_closed';
                    $closed = true;
                } else {
                    $state = 'rule_pending';
                    $closed = false;
                    $cutoffLabel = $cutoff->translatedFormat('D, H:i');
                }
            } else {
                $state = 'open';
                $closed = false;
            }

            $out[$iso] = [
                'state' => $state,
                'closed' => $closed,
                'has_rule' => $hasRule,
                'has_override' => $manualClosed || $forcedOpen,
                'cutoff_label' => $cutoffLabel,
            ];

            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  Collection<int, array{date: Carbon, product_count?: int, product_ids?: int[]}>  $dates
     * @return Collection<int, array{date: Carbon, product_count?: int, product_ids?: int[]}>
     */
    public function filterOpenDates(Collection $dates): Collection
    {
        return $dates
            ->filter(fn (array $row): bool => ! $this->isClosed($row['date']))
            ->values();
    }
}
