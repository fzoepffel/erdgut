<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxPlannerCanonicalCustomizeRequest;
use App\Http\Requests\Admin\AdminBoxPlannerWeekRequest;
use App\Models\Box;
use App\Models\Product;
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
            $nominalTtc = round((float) $subscription->nominalIncludedValueTtc(), 2);
            $trialBudgetNotice = $subscription->isTrialBillingPending()
                && abs($nominalTtc - $includedBudget) > 0.005;
            $planningHint = $marginRate > 0 && $marginRate < 1
                ? round($includedBudget * (1 - $marginRate), 2)
                : null;

            $row = [
                'subscription' => $subscription,
                'included_budget' => $includedBudget,
                'nominal_included_ttc' => $nominalTtc,
                'trial_budget_notice' => $trialBudgetNotice,
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

        $boxIds = collect($matrix)
            ->flatMap(fn (array $r) => collect($r['cells'])->pluck('box')->filter()->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $boxesById = collect();
        if ($boxIds !== []) {
            $boxesById = Box::query()
                ->whereIn('id', $boxIds)
                ->with([
                    'items' => fn ($q) => $q->orderBy('id')->with('product:id,name'),
                ])
                ->get()
                ->keyBy('id');
        }

        foreach ($matrix as &$row) {
            foreach ($row['cells'] as &$cell) {
                $box = $cell['box'];
                if ($box === null) {
                    $cell['items_summary'] = null;

                    continue;
                }
                $full = $boxesById->get($box->id);
                $cell['items_summary'] = $full !== null
                    ? $this->formatBoxItemsSummary($full)
                    : null;
            }
        }
        unset($row, $cell);

        return view('admin.operations.boxes.planner', [
            'weeks' => $weeks,
            'matrix' => $matrix,
            'planningMarginRate' => $marginRate,
        ]);
    }

    public function canonicalPreview(AdminBoxPlannerWeekRequest $request, AdminBoxPlannerFillService $fillService): View
    {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $preview = $fillService->previewCanonical($week);

        $catalogProducts = Product::query()
            ->visibleInCatalog()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.operations.boxes.canonical-preview', [
            'week' => $week,
            'preview' => $preview,
            'catalogProducts' => $catalogProducts,
        ]);
    }

    public function canonicalApply(AdminBoxPlannerCanonicalCustomizeRequest $request, AdminBoxPlannerFillService $fillService): RedirectResponse
    {
        $this->authorize('admin');

        $week = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $templates = $request->validated('templates');
        $updated = $fillService->applyCanonicalFromRequest($week, $templates);

        return redirect()
            ->route('app.admin.boxes.planner')
            ->with('status', __('admin.box_planner.flash_canonical_applied', ['count' => $updated]));
    }

    private function formatBoxItemsSummary(Box $box): string
    {
        $parts = [];
        foreach ($box->items as $item) {
            $name = $item->product?->name ?? '—';
            $qty = (float) $item->quantity;
            $qtyStr = $this->formatQuantityForSummary($qty);
            $parts[] = '('.$qtyStr.'×) '.$name;
        }

        return implode(', ', $parts);
    }

    private function formatQuantityForSummary(float $quantity): string
    {
        $rounded = round($quantity, 4);
        if (abs($rounded - round($rounded)) < 0.0001) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 2, ',', '.'), '0'), ',');
    }
}
