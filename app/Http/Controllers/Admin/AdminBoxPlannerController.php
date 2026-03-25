<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxPlannerFillApplyRequest;
use App\Http\Requests\Admin\AdminBoxPlannerWeekRequest;
use App\Models\Box;
use App\Models\Subscription;
use App\Services\Admin\AdminBoxPlannerFillService;
use App\Services\Admin\WeeklyBoxPlannerService;
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

        $marginRate = (float) config('box.admin_planning_margin_rate', 0);

        $matrix = [];
        foreach ($subscriptions as $subscription) {
            $includedBudget = round((float) $subscription->includedValueForBudget(), 2);
            $planningHint = $marginRate > 0 && $marginRate < 1
                ? round($includedBudget * (1 - $marginRate), 2)
                : null;

            $row = [
                'subscription' => $subscription,
                'included_budget' => $includedBudget,
                'planning_hint' => $planningHint,
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

                $estimatedValue = null;
                $overBudget = false;
                if ($box !== null) {
                    $estimatedValue = round((float) $box->estimated_value, 2);
                    $overBudget = $estimatedValue > $includedBudget + 0.005;
                }

                $row['cells'][] = [
                    'week' => $week,
                    'eligible' => $eligible,
                    'box' => $box,
                    'estimated_value' => $estimatedValue,
                    'over_budget' => $overBudget,
                ];
            }
            $matrix[] = $row;
        }

        return view('admin.operations.boxes.planner', [
            'weeks' => $weeks,
            'matrix' => $matrix,
            'planningMarginRate' => $marginRate,
        ]);
    }

    public function bulkRandomPreview(AdminBoxPlannerWeekRequest $request, AdminBoxPlannerFillService $fillService): View
    {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $preview = $fillService->previewBulkRandom($week);

        return view('admin.operations.boxes.bulk-random-preview', [
            'week' => $week,
            'preview' => $preview,
        ]);
    }

    public function bulkRandomApply(AdminBoxPlannerFillApplyRequest $request, AdminBoxPlannerFillService $fillService): RedirectResponse
    {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $filled = $fillService->applyBulkRandom($week);

        return redirect()
            ->route('app.admin.boxes.planner')
            ->with('status', __('admin.box_planner.flash_bulk_random_applied', ['count' => $filled]));
    }

    public function canonicalPreview(AdminBoxPlannerWeekRequest $request, AdminBoxPlannerFillService $fillService): View
    {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $preview = $fillService->previewCanonical($week);

        return view('admin.operations.boxes.canonical-preview', [
            'week' => $week,
            'preview' => $preview,
        ]);
    }

    public function canonicalApply(AdminBoxPlannerFillApplyRequest $request, AdminBoxPlannerFillService $fillService): RedirectResponse
    {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $updated = $fillService->applyCanonical($week);

        return redirect()
            ->route('app.admin.boxes.planner')
            ->with('status', __('admin.box_planner.flash_canonical_applied', ['count' => $updated]));
    }
}
