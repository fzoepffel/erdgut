<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoxStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxPresetRequest;
use App\Http\Requests\Admin\AdminBoxStoreRequest;
use App\Models\Box;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Support\BoxCompositionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class AdminBoxController extends Controller
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner
    ) {}

    public function show(Box $box): View
    {
        $this->authorize('admin');

        $box->load([
            'user:id,email',
            'items' => fn ($q) => $q->orderBy('id')->with('product:id,name'),
        ]);

        $catalogProducts = Product::query()
            ->visibleInCatalog()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.operations.boxes.show', [
            'box' => $box,
            'catalogProducts' => $catalogProducts,
            'adminCanMutateItems' => $box->allowsAdminLineEdits(),
        ]);
    }

    public function store(AdminBoxStoreRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $subscription = Subscription::query()->findOrFail($validated['subscription_id']);
        $week = Carbon::parse($validated['week_start_date'])->startOfDay();

        $existed = $this->weeklyBoxPlanner->boxExistsForUserWeek($subscription->user_id, $week);
        $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $week);

        $message = $existed
            ? __('admin.box_planner.flash_box_exists')
            : __('admin.box_planner.flash_box_created');

        return redirect()
            ->route('app.admin.boxes.planner')
            ->with('status', $message);
    }

    public function preset(AdminBoxPresetRequest $request, Box $box, BoxCompositionService $compositionService): RedirectResponse
    {
        $this->authorize('admin');

        if ($box->status !== BoxStatus::Editable) {
            return redirect()
                ->back()
                ->withErrors(['box' => __('admin.box_planner.errors.box_not_editable')]);
        }

        $subscription = Subscription::query()->findOrFail($request->validated()['subscription_id']);
        $compositionService->composePresetFromScratch($box, (string) $subscription->box_size);

        return redirect()
            ->back()
            ->with('status', __('admin.box_planner.flash_preset_applied'));
    }
}
