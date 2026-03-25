<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxStoreRequest;
use App\Models\Box;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
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

        $subscription = Subscription::query()
            ->where('user_id', $box->user_id)
            ->where('status', SubscriptionStatus::Active)
            ->latest()
            ->first();

        $marginRate = (float) config('box.admin_planning_margin_rate', 0);
        $budgetDetails = null;
        if ($subscription !== null) {
            $included = round((float) $subscription->includedValueForBudget(), 2);
            $estimated = round((float) $box->estimated_value, 2);
            $budgetDetails = [
                'included_budget' => $included,
                'estimated_value' => $estimated,
                'remainder' => round($included - $estimated, 2),
                'over_budget' => $estimated > $included + 0.005,
                'planning_hint' => $marginRate > 0 && $marginRate < 1
                    ? round($included * (1 - $marginRate), 2)
                    : null,
            ];
        }

        return view('admin.operations.boxes.show', [
            'box' => $box,
            'catalogProducts' => $catalogProducts,
            'adminCanMutateItems' => $box->allowsAdminLineEdits(),
            'budgetDetails' => $budgetDetails,
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
}
