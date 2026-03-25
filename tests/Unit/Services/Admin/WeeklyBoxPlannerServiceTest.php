<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WeeklyBoxPlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_weeks_ahead_defaults_when_config_key_missing(): void
    {
        Config::set('admin.box_planner_weeks_ahead', null);

        $this->assertSame(4, WeeklyBoxPlannerService::configWeeksAhead());
    }

    public function test_horizon_week_starts_does_not_require_explicit_count_when_config_null(): void
    {
        Config::set('admin.box_planner_weeks_ahead', null);

        $service = $this->app->make(WeeklyBoxPlannerService::class);
        $weeks = $service->horizonWeekStarts(Carbon::parse('2026-03-30')->startOfDay());

        $this->assertCount(4, $weeks);
    }
}
