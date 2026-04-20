<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxSetupApplyRequest;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Services\Admin\WeeklyBoxSetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AdminBoxSetupController extends Controller
{
    public function __construct(
        private readonly WeeklyBoxSetupService $setupService
    ) {}

    public function index(): View
    {
        $this->authorize('admin');

        return view('admin.operations.boxes.index', [
            'weeks' => $this->setupService->calendarOverview(),
        ]);
    }

    public function show(string $week): View
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);
        $data = $this->setupService->buildInitialDistribution($monday);

        return view('admin.operations.boxes.setup', [
            'week' => $monday,
            'data' => $data,
        ]);
    }

    public function apply(AdminBoxSetupApplyRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $monday = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $sizes = $request->validated('sizes');

        $updated = $this->setupService->applyDistribution($monday, $sizes);

        return redirect()
            ->route('app.admin.boxes.index')
            ->with('status', __('admin.box_setup.flash_applied', ['count' => $updated]));
    }

    private function resolveMonday(string $week): Carbon
    {
        $candidate = Carbon::parse($week)->startOfDay();
        if (! $candidate->isMonday()) {
            return WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
        }

        return $candidate;
    }
}
