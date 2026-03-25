<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxPlannerBulkPresetRequest;
use App\Models\Box;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Support\BoxCompositionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AdminBoxPlannerController extends Controller
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner
    ) {}

    public function index(): View
    {
        $this->authorize('admin');

        $firstMonday = WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
        $weeks = $this->weeklyBoxPlanner->horizonWeekStarts($firstMonday);

        $subscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('user:id,email')
            ->orderBy('id')
            ->get();

        $matrix = [];
        foreach ($subscriptions as $subscription) {
            $row = [
                'subscription' => $subscription,
                'cells' => [],
            ];
            foreach ($weeks as $week) {
                $eligible = $this->weeklyBoxPlanner->isEligibleForWeek($subscription, $week);
                $box = null;
                if ($eligible) {
                    $box = Box::query()
                        ->forUser($subscription->user_id)
                        ->forWeek($week->toDateString())
                        ->first();
                }
                $row['cells'][] = [
                    'week' => $week,
                    'eligible' => $eligible,
                    'box' => $box,
                ];
            }
            $matrix[] = $row;
        }

        return view('admin.operations.boxes.planner', [
            'weeks' => $weeks,
            'matrix' => $matrix,
        ]);
    }

    public function bulkPreset(AdminBoxPlannerBulkPresetRequest $request, BoxCompositionService $compositionService): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $week = Carbon::parse($validated['week_start_date'])->startOfDay();

        $filled = 0;
        foreach ($this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($week) as $subscription) {
            $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $week);
            if ($box->status !== BoxStatus::Editable) {
                continue;
            }
            $compositionService->composePresetFromScratch($box, (string) $subscription->box_size);
            $filled++;
        }

        return redirect()
            ->route('app.admin.boxes.planner')
            ->with('status', __('admin.box_planner.flash_bulk_preset', ['count' => $filled]));
    }
}
