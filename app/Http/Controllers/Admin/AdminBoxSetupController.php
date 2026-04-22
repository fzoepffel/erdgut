<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxSetupApplyRequest;
use App\Models\Box;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Services\Admin\WeeklyBoxSetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AdminBoxSetupController extends Controller
{
    public function __construct(
        private readonly WeeklyBoxSetupService $setupService,
        private readonly WeeklyBoxPlannerService $plannerService,
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

    public function weekBoxes(string $week): View
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);

        $boxes = Box::query()
            ->forWeek($monday->toDateString())
            ->with([
                'user:id,email,name',
                'order:id,box_size',
                'items:id,box_id,is_extra',
            ])
            ->orderBy('id')
            ->get();

        $subsByUserId = $this->plannerService
            ->eligibleSubscriptionsForWeek($monday)
            ->keyBy(fn (Subscription $s) => (int) $s->user_id);

        $rows = $boxes->map(function (Box $box) use ($subsByUserId): array {
            $sub = $subsByUserId->get((int) $box->user_id);
            $items = $box->items;
            $boxSize = $sub?->box_size ?? $box->order?->box_size;

            return [
                'box' => $box,
                'box_size' => $boxSize !== null ? (string) $boxSize : null,
                'non_extra_count' => $items->where('is_extra', false)->count(),
                'extra_count' => $items->where('is_extra', true)->count(),
            ];
        })->values()->all();

        return view('admin.operations.boxes.week', [
            'week' => $monday,
            'rows' => $rows,
        ]);
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
