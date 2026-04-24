<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlannerHorizonTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_horizon_is_eight_weeks(): void
    {
        config()->set('admin.box_planner_weeks_ahead', 8);

        $this->assertSame(8, WeeklyBoxPlannerService::configWeeksAhead());
    }

    public function test_horizon_is_capped_at_twelve(): void
    {
        config()->set('admin.box_planner_weeks_ahead', 999);

        $this->assertSame(12, WeeklyBoxPlannerService::configWeeksAhead());
    }

    public function test_horizon_supports_up_to_twelve(): void
    {
        config()->set('admin.box_planner_weeks_ahead', 12);

        $this->assertSame(12, WeeklyBoxPlannerService::configWeeksAhead());
    }

    public function test_admin_planner_index_renders_compact_grid_partial(): void
    {
        config()->set('admin.box_planner_weeks_ahead', 12);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.index'))
            ->assertOk()
            ->assertSee(__('admin.box_setup.compact_grid_title'));
    }
}
