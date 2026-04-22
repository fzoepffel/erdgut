<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminTrialBoxSetupApplyRequest;
use App\Http\Requests\Admin\AdminTrialBoxSetupShowRequest;
use App\Models\Order;
use App\Services\Admin\TrialWeeklyBoxSetupService;
use App\Services\Admin\WeeklyBoxPlannerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminTrialBoxSetupController extends Controller
{
    public function __construct(
        private readonly TrialWeeklyBoxSetupService $setupService,
    ) {}

    public function select(Request $request): View
    {
        $this->authorize('admin');

        $week = $this->resolveMonday($request->string('week')->toString());

        $status = $request->string('status')->toString();
        $statusValues = array_column(OrderStatus::cases(), 'value');
        if (! in_array($status, $statusValues, true)) {
            $status = '';
        }

        $ordersQuery = Order::query()
            ->where('product_type', OrderProductType::TrialBox->value)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Scheduled->value])
            ->with(['user:id,email,name'])
            ->latest();

        if ($status !== '') {
            $ordersQuery->where('status', $status);
        }

        $orders = $ordersQuery->paginate(25)->withQueryString();

        return view('admin.operations.trial_boxes.select', [
            'week' => $week,
            'orders' => $orders,
            'status' => $status,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(string $week, AdminTrialBoxSetupShowRequest $request): View
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);
        $orderIds = $request->orderIds();

        $data = $this->setupService->buildInitialDistribution($monday, $orderIds);

        return view('admin.operations.trial_boxes.setup', [
            'week' => $monday,
            'orderIds' => $orderIds,
            'data' => $data,
        ]);
    }

    public function apply(string $week, AdminTrialBoxSetupApplyRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $monday = Carbon::parse($request->validated('week_start_date'))->startOfDay();
        $orderIds = $request->orderIds();
        $sizes = $request->sizeLines();

        $updated = $this->setupService->applyDistribution($monday, $orderIds, $sizes);

        return redirect()
            ->route('app.admin.trial-boxes.setup.select', ['week' => $monday->toDateString()])
            ->with('status', __('admin.trial_box_setup.flash_applied', ['count' => $updated]));
    }

    private function resolveMonday(string $week): Carbon
    {
        if ($week !== '') {
            $candidate = Carbon::parse($week)->startOfDay();
            if ($candidate->isMonday()) {
                return $candidate;
            }
        }

        return WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
    }
}
