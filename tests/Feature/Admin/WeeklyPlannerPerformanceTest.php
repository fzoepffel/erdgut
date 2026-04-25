<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\WeeklyBoxSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ERD-344: regression test against query thrash on the 12-week planner
 * horizon. Before the fix, calendarOverview() ran 2 queries per week
 * (subscriptions + boxes) — 24+ queries for a 12-week horizon. After the
 * fix the queries are hoisted out of the loop. We assert a hard upper
 * bound that won't drift by accident.
 */
class WeeklyPlannerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_overview_does_not_thrash_queries_on_twelve_week_horizon(): void
    {
        config(['box.planner_weeks_ahead' => 12]);

        $users = User::factory()->count(5)->create();
        foreach ($users as $u) {
            Subscription::factory()->create([
                'user_id' => $u->id,
                'status' => SubscriptionStatus::Active,
                'box_size' => 'medium',
            ]);
        }

        // Seed a few boxes scattered across the horizon so the query has work
        // to do — without rows the optimizer might short-circuit.
        Box::factory()->count(4)->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $rows = app(WeeklyBoxSetupService::class)->calendarOverview(12);

        $this->assertCount(12, $rows);
        $this->assertLessThanOrEqual(
            8,
            $queryCount,
            "calendarOverview() ran {$queryCount} queries for a 12-week horizon. ".
            'The hoist optimization caps this at ~2-4 queries (subs, boxes, plus framework overhead). '.
            'A regression here means per-week queries crept back into the loop.'
        );
    }
}
