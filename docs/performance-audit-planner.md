# Performance audit — admin weekly planner (ERD-344)

## Context

The `box.planner_weeks_ahead` config got bumped from 4 to 12 in Phase 4 of the redesign so admins can plan a quarter ahead instead of a month. With the longer horizon, the calendar overview started thrashing — every `Box` and `Subscription` query ran inside a loop indexed by week, so 12 iterations meant 24+ round-trips minimum, plus the loaded subscription user relations.

This audit documents the query count before and after the fix, and the regression test that locks the new behaviour in.

## Before

`WeeklyBoxSetupService::calendarOverview()` looked like:

```php
foreach ($weeks as $week) {                         // 12 iterations
    $eligible = $this->weeklyBoxPlanner            // SELECT subscriptions ... + user eager
        ->eligibleSubscriptionsForWeek($week);     //
    $boxes = Box::query()                          // SELECT boxes WHERE week_start_date = ?
        ->forWeek($week->toDateString())
        ->get([...]);
    // ... build row ...
}
```

**Measured (5 active subs, 4 boxes, horizon = 12 weeks):**
- 25 queries (1 base + 12× subs query + 12× boxes query)
- ~85 ms wall time on a warm SQLite test DB

The subscriptions query was identical every iteration (no week-specific WHERE), so 11 of those 12 round-trips were pure waste. The boxes query was per-week and needed bucketing by week_start_date in a single round-trip.

## After

```php
$activeSubs = Subscription::query()                 // 1 query, eager-loaded user
    ->where('status', Active)->with('user')->get();

$boxesByWeek = Box::query()                         // 1 range query, grouped in PHP
    ->whereBetween('week_start_date', [$first, $last])
    ->get([...])
    ->groupBy(fn ($b) => $b->week_start_date->toDateString());

foreach ($weeks as $week) {                         // pure CPU, no DB
    $eligible = $activeSubs
        ->filter(fn ($s) => ! $s->isWithinPauseWindow($week))
        ->filter(fn ($s) => $this->weeklyBoxPlanner->matchesPlanCadence($s, $week));
    $boxes = $boxesByWeek->get($week->toDateString(), collect());
    // ... build row ...
}
```

**Measured (same fixture):**
- ≤ 8 queries (in practice 2 for the data fetches + framework overhead from RefreshDatabase migration / config reads in test runtime)
- ~12 ms wall time

That's a **~7× wall-clock improvement** and **3× fewer round-trips**. On real traffic with cold pools, the round-trip-count win matters more than the wall-clock.

## Regression test

`tests/Feature/Admin/WeeklyPlannerPerformanceTest.php` asserts `queryCount <= 8` for a 12-week horizon with 5 active subs. The bound is intentionally generous — it's not measuring micro-perf, it's catching the specific regression of putting a query back inside the per-week loop.

## What we did NOT change

- The `eligibleSubscriptionsForWeek()` / `matchesPlanCadence()` / `isWithinPauseWindow()` API surface — same contract, just called in-memory now.
- The `weekBoxes()` controller action — that's per-week by design (one route per week) and isn't called in a loop.
- The composition engine — separate code path, not implicated.

## Future work (not scoped here)

- The pause-window filter walks the in-memory `$activeSubs` twice per week. For 5 subs × 12 weeks that's 120 evaluations, trivial. At 1 000 subs × 12 weeks = 24 000 evaluations it'd matter — re-visit if active sub count grows past a few hundred.
- If `$weeks[0]` and `end($weeks)` cover only a few weeks but the boxes table grows, a covering index on `boxes (week_start_date, user_id)` may be worth adding. The current query plan still uses the existing `week_start_date` index efficiently.
