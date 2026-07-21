<?php

namespace App\Services\Shop;

use App\Mail\ShopOfferReopenedMail;
use App\Models\ShopOfferSubscriber;
use App\Models\ShopReducedOfferPeriod;
use App\Models\ShopReducedOfferRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * "Reduced offer" mode for the public shop.
 *
 * When only a few repeating inflows are in stock (e.g. flour for several
 * weeks), the shop looks empty and drives customers away. Instead of hard
 * closing, the storefront stays shoppable but shows a banner + countdown until
 * the full offer returns, plus an email waitlist.
 *
 * Whether the shop is in reduced mode *right now* is derived live (like
 * {@see ShopClosureService}): from active manual periods and recurring rules.
 * Nothing is materialised for display, so a missed cron never drifts the
 * banner. Period rows are written only to make the reopen email idempotent
 * (exactly one send per reduced cycle).
 */
class ShopReducedOfferService
{
    /** @var Collection<int, ShopReducedOfferRule>|null Memoised per request. */
    private ?Collection $activeRules = null;

    /**
     * @return Collection<int, ShopReducedOfferRule>
     */
    public function activeRules(): Collection
    {
        return $this->activeRules ??= ShopReducedOfferRule::query()
            ->where('is_active', true)
            ->get();
    }

    public function flushRuleCache(): void
    {
        $this->activeRules = null;
    }

    /**
     * Is the shop in reduced-offer mode at $at (default now)?
     */
    public function isReduced(?Carbon $at = null): bool
    {
        return $this->currentWindow($at) !== null;
    }

    /**
     * The moment the full offer returns for the active window (countdown target),
     * or null when the shop is not reduced.
     */
    public function reopenAt(?Carbon $at = null): ?Carbon
    {
        $window = $this->currentWindow($at);

        return $window['reopens_at'] ?? null;
    }

    /**
     * The active reduced window at $at, or null. Manual periods win over rules.
     *
     * This is read on every public shop render (and its error pages), so a
     * database problem (e.g. the tables not yet migrated) must never take the
     * shop down over a decorative banner: on a query failure we simply show no
     * banner and log it.
     *
     * @return array{starts_at: Carbon, reopens_at: Carbon, source: string, rule: ?ShopReducedOfferRule, period: ?ShopReducedOfferPeriod}|null
     */
    public function currentWindow(?Carbon $at = null): ?array
    {
        try {
            return $this->resolveWindow($at);
        } catch (QueryException $e) {
            Log::warning('Reduced-offer window lookup failed; hiding banner.', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{starts_at: Carbon, reopens_at: Carbon, source: string, rule: ?ShopReducedOfferRule, period: ?ShopReducedOfferPeriod}|null
     */
    private function resolveWindow(?Carbon $at): ?array
    {
        $now = $at?->copy() ?? Carbon::now();

        // 1. Manual period wins. Active = started, not yet reopened, not opened
        //    early. A future-scheduled manual period (opened_at null) counts
        //    only once starts_at has passed.
        $manual = ShopReducedOfferPeriod::query()
            ->where('source', 'manual')
            ->where('starts_at', '<=', $now)
            ->where('reopens_at', '>', $now)
            ->where(fn ($q) => $q->whereNull('opened_at')->orWhere('opened_at', '>', $now))
            ->orderBy('reopens_at')
            ->first();

        if ($manual !== null) {
            return [
                'starts_at' => $manual->starts_at->copy(),
                'reopens_at' => $manual->reopens_at->copy(),
                'source' => 'manual',
                'rule' => null,
                'period' => $manual,
            ];
        }

        // 2. Recurring rules. Pick the active window with the soonest reopen.
        $best = null;
        foreach ($this->activeRules() as $rule) {
            $window = $this->windowForRule($rule, $now);
            if ($window === null) {
                continue;
            }

            // A recurring window opened early by an admin stops being reduced.
            $marker = ShopReducedOfferPeriod::query()
                ->where('source', 'recurring')
                ->where('rule_id', $rule->id)
                ->whereBetween('starts_at', [$window['starts_at']->copy()->subMinute(), $window['starts_at']->copy()->addMinute()])
                ->first();
            if ($marker !== null && $marker->opened_at !== null && $marker->opened_at->lte($now)) {
                continue;
            }

            if ($best === null || $window['reopens_at']->lt($best['reopens_at'])) {
                $best = [
                    'starts_at' => $window['starts_at'],
                    'reopens_at' => $window['reopens_at'],
                    'source' => 'recurring',
                    'rule' => $rule,
                    'period' => $marker,
                ];
            }
        }

        return $best;
    }

    /**
     * The active recurring window covering $now for $rule, or null.
     *
     * @return array{starts_at: Carbon, reopens_at: Carbon}|null
     */
    private function windowForRule(ShopReducedOfferRule $rule, Carbon $now): ?array
    {
        $start = $this->mostRecentInstant($now, (int) $rule->starts_weekday, (string) $rule->starts_time);
        if ($start === null) {
            return null;
        }

        $reopen = $this->nextInstant($start, (int) $rule->reopens_weekday, (string) $rule->reopens_time);

        if ($now->gte($reopen)) {
            return null; // window already reopened; we're in the full-offer stretch
        }

        return ['starts_at' => $start, 'reopens_at' => $reopen];
    }

    /**
     * The latest instant on/before $now that falls on $weekday at $time.
     */
    private function mostRecentInstant(Carbon $now, int $weekday, string $time): ?Carbon
    {
        for ($k = 0; $k <= 7; $k++) {
            $candidate = $now->copy()->subDays($k)->setTimeFromTimeString($time);
            if ((int) $candidate->dayOfWeek === $weekday && $candidate->lte($now)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The first instant strictly after $after that falls on $weekday at $time.
     */
    private function nextInstant(Carbon $after, int $weekday, string $time): Carbon
    {
        for ($k = 0; $k <= 8; $k++) {
            $candidate = $after->copy()->addDays($k)->setTimeFromTimeString($time);
            if ((int) $candidate->dayOfWeek === $weekday && $candidate->gt($after)) {
                return $candidate;
            }
        }

        // Unreachable for a valid weekday, but keep a total return.
        return $after->copy()->addWeek();
    }

    // ---- Admin actions -----------------------------------------------------

    /**
     * Start a one-off reduced period (or schedule one). Fires no email; the
     * email goes out when it reopens.
     */
    public function startManual(Carbon $startsAt, Carbon $reopensAt, ?int $userId = null): ShopReducedOfferPeriod
    {
        return ShopReducedOfferPeriod::query()->create([
            'starts_at' => $startsAt,
            'reopens_at' => $reopensAt,
            'source' => 'manual',
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Reopen the shop now, ending the active reduced window early. Returns the
     * period that was opened (so the caller can notify the waitlist), or null
     * when the shop was not reduced.
     */
    public function openNow(?int $userId = null): ?ShopReducedOfferPeriod
    {
        $now = Carbon::now();
        $window = $this->currentWindow($now);
        if ($window === null) {
            return null;
        }

        if ($window['source'] === 'manual') {
            $period = $window['period'];
            $period->opened_at = $now;
            $period->save();

            return $period;
        }

        // Recurring: materialise (or reuse) a marker row for this cycle.
        $period = ShopReducedOfferPeriod::query()->firstOrCreate(
            [
                'source' => 'recurring',
                'rule_id' => $window['rule']->id,
                'starts_at' => $window['starts_at'],
            ],
            [
                'reopens_at' => $window['reopens_at'],
                'created_by_user_id' => $userId,
            ],
        );
        $period->opened_at = $now;
        $period->save();

        return $period;
    }

    /**
     * Process windows that have reopened and not yet notified the waitlist.
     * Called by the scheduled command; safe to run repeatedly.
     *
     * @return int Number of periods newly notified.
     */
    public function processDueReopens(?Carbon $at = null): int
    {
        $now = $at?->copy() ?? Carbon::now();
        $notified = 0;

        // Manual periods that auto-reopened (were not opened early via open-now).
        $dueManual = ShopReducedOfferPeriod::query()
            ->where('source', 'manual')
            ->whereNull('notified_at')
            ->whereNull('opened_at')
            ->where('reopens_at', '<=', $now)
            ->get();
        foreach ($dueManual as $period) {
            $period->opened_at = $period->reopens_at;
            $this->notifySubscribers($period);
            $notified++;
        }

        // Recurring windows whose reopen just passed. Only look back a little so
        // a fresh deploy doesn't blast for historical cycles.
        foreach ($this->activeRules() as $rule) {
            $reopen = $this->mostRecentInstant($now, (int) $rule->reopens_weekday, (string) $rule->reopens_time);
            if ($reopen === null || $reopen->lt($now->copy()->subDays(8)) || $reopen->gt($now)) {
                continue;
            }
            $start = $this->mostRecentInstant($reopen->copy()->subSecond(), (int) $rule->starts_weekday, (string) $rule->starts_time);
            if ($start === null || ! $this->nextInstant($start, (int) $rule->reopens_weekday, (string) $rule->reopens_time)->equalTo($reopen)) {
                continue;
            }

            $period = ShopReducedOfferPeriod::query()->firstOrCreate(
                ['source' => 'recurring', 'rule_id' => $rule->id, 'starts_at' => $start],
                ['reopens_at' => $reopen, 'created_by_user_id' => null],
            );
            if ($period->notified_at !== null) {
                continue; // already handled (auto or open-now)
            }
            if ($period->opened_at === null) {
                $period->opened_at = $reopen;
            }
            $this->notifySubscribers($period);
            $notified++;
        }

        return $notified;
    }

    /**
     * Send the "full offer is back" email to every waitlist subscriber and mark
     * the period notified. Idempotent: a period is notified at most once.
     *
     * Sent synchronously in chunks with a per-recipient try/catch so a single
     * bad address can't 500 the request (staging runs the queue inline).
     *
     * @return int Number of emails sent.
     */
    public function notifySubscribers(ShopReducedOfferPeriod $period): int
    {
        if ($period->notified_at !== null) {
            return 0;
        }

        $sent = 0;
        $now = Carbon::now();

        ShopOfferSubscriber::query()->orderBy('id')->chunkById(100, function (Collection $subscribers) use (&$sent, $now): void {
            foreach ($subscribers as $subscriber) {
                try {
                    Mail::to($subscriber->email)->send(new ShopOfferReopenedMail($subscriber));
                    $subscriber->forceFill(['last_notified_at' => $now])->save();
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Reduced-offer reopen email failed', [
                        'subscriber_id' => $subscriber->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $period->notified_at = $now;
        $period->save();

        return $sent;
    }

    // ---- Waitlist ----------------------------------------------------------

    /**
     * Add an email to the waitlist (idempotent on email).
     */
    public function subscribe(string $email): ShopOfferSubscriber
    {
        return ShopOfferSubscriber::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($email))],
        );
    }

    /**
     * Remove a subscriber by token. Returns true if a row was deleted.
     */
    public function unsubscribeByToken(string $token): bool
    {
        return ShopOfferSubscriber::query()->where('unsubscribe_token', $token)->delete() > 0;
    }

    public function subscriberCount(): int
    {
        return ShopOfferSubscriber::query()->count();
    }

    /**
     * The waitlist, for the admin overview: newest signups first.
     *
     * @return list<array{email: string, since: ?string}>
     */
    public function subscriberList(): array
    {
        return ShopOfferSubscriber::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['email', 'created_at'])
            ->map(fn (ShopOfferSubscriber $subscriber): array => [
                'email' => $subscriber->email,
                'since' => $subscriber->created_at?->format('d.m.Y'),
            ])
            ->all();
    }
}
