<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxDistributeRequest;
use App\Services\Admin\EqualBoxDistributionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AdminBoxDistributionController extends Controller
{
    public function show(
        AdminBoxDistributeRequest $request,
        EqualBoxDistributionService $distributionService
    ): View {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $preview = $distributionService->buildPreview($week);

        return view('admin.operations.boxes.distribute', [
            'week' => $week,
            'preview' => $preview,
        ]);
    }

    public function apply(
        AdminBoxDistributeRequest $request,
        EqualBoxDistributionService $distributionService
    ): RedirectResponse {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $result = $distributionService->apply($week);

        return redirect()
            ->route('app.admin.boxes.planner')
            ->with('status', __('admin.box_distribute.flash_applied', [
                'updated' => $result['boxes_updated'],
                'skipped' => $result['skipped_locked_count'],
            ]));
    }
}
