<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Mail\TrialDeliveryScheduledMail;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Support\BoxCompositionService;
use App\Support\DeliveryProjector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $status = $request->string('status')->toString();
        $productType = $request->string('product_type')->toString();

        $query = Order::query()
            ->with(['user:id,email,name', 'address:id,street_line_1,postal_code,city'])
            ->latest();

        if ($productType !== '' && in_array($productType, array_column(OrderProductType::cases(), 'value'), true)) {
            $query->where('product_type', $productType);
        }

        if ($status !== '' && in_array($status, array_column(OrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($request->boolean('needs_schedule')) {
            $query->whereNull('scheduled_for_date');
        }

        if ($request->boolean('needs_box')) {
            $query->whereNull('box_id');
        }

        if ($request->boolean('ready_to_deliver')) {
            $query->whereNotNull('box_id');
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'productType' => $productType,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('admin');

        $order->load([
            'user',
            'address',
            'box.items.product',
            'box.items.product.translations',
            'deliveries.address',
            'payments' => fn ($q) => $q->latest(),
        ]);

        $trialSetupWeek = null;
        $bulkSetupUrl = null;
        $catalogProducts = collect();
        $budgetDetails = null;
        $adminCanMutateItems = false;

        $isTrial = $order->product_type === OrderProductType::TrialBox;
        $isEligible = $isTrial && in_array($order->status, [OrderStatus::Paid, OrderStatus::Scheduled], true);
        $hasBoxWithItems = $order->box !== null && $order->box->items->where('is_extra', false)->isNotEmpty();

        if ($isEligible && ! $hasBoxWithItems) {
            $trialSetupWeek = $this->resolveMondayForTrialSetup($order, request()->string('week')->toString());
            $bulkSetupUrl = route('app.admin.trial-boxes.setup.show', [
                'week' => $trialSetupWeek->toDateString(),
                'order_ids' => [$order->id],
            ]);
        }

        if ($isEligible && $order->box !== null) {
            $box = $order->box;
            $adminCanMutateItems = $box->allowsAdminLineEdits();

            $catalogQuery = Product::query();
            if ($box->week_start_date !== null) {
                $catalogQuery->availableForWeek($box->week_start_date);
            } else {
                $catalogQuery->visibleInCatalog();
            }

            $locale = app()->getLocale();
            $catalogProducts = $catalogQuery
                ->select('products.*')
                ->join('product_translations as pt', function ($join) use ($locale): void {
                    $join->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
                })
                ->with('translations')
                ->orderBy('pt.name')
                ->get(['products.id']);

            $included = round((float) config('box.included_value_by_size.'.(string) $order->box_size, 0), 2);
            $estimated = round((float) ($box->estimated_value ?? 0), 2);
            $marginRate = (float) config('box.admin_planning_margin_rate', 0);
            $planningHint = $marginRate > 0 && $marginRate < 1
                ? round($included * (1 - $marginRate), 2)
                : null;

            $budgetDetails = [
                'included_budget' => $included,
                'estimated_value' => $estimated,
                'remainder' => round($included - $estimated, 2),
                'over_budget' => $estimated > $included + 0.005,
                'planning_hint' => $planningHint,
            ];
        }

        return view('admin.orders.show', [
            'order' => $order,
            'timeslots' => Delivery::allowedTimeslots(),
            'trialSetupWeek' => $trialSetupWeek,
            'bulkSetupUrl' => $bulkSetupUrl,
            'catalogProducts' => $catalogProducts,
            'adminCanMutateItems' => $adminCanMutateItems,
            'budgetDetails' => $budgetDetails,
        ]);
    }

    public function schedule(Request $request, Order $order, DeliveryProjector $projector): RedirectResponse
    {
        $this->authorize('admin');

        if ($order->product_type === OrderProductType::TrialBox) {
            $order->loadMissing(['box.items' => fn ($q) => $q->where('is_extra', false)]);
            $hasBoxWithItems = $order->box !== null && $order->box->items->isNotEmpty();
            if (! $hasBoxWithItems) {
                return back()->with('status', __('admin.trial_order_schedule.validation_needs_box'));
            }
        }

        $validated = $request->validate([
            'scheduled_for_date' => ['required', 'date', 'after_or_equal:today'],
            'timeslot' => ['required', 'string', 'in:'.implode(',', Delivery::allowedTimeslots())],
        ]);

        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Refunded) {
            return back()->with('status', 'order-not-schedulable');
        }

        $date = Carbon::parse($validated['scheduled_for_date']);

        DB::transaction(function () use ($order, $date, $validated, $projector) {
            $order->markScheduled($date, $validated['timeslot']);
            $projector->projectForTrialOrder($order->fresh(), $date, $validated['timeslot']);
        });

        if ($order->user) {
            Mail::to($order->user->email)->queue(new TrialDeliveryScheduledMail($order->fresh()));
        }

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'order-scheduled');
    }

    public function compose(Request $request, Order $order, BoxCompositionService $composer): RedirectResponse
    {
        $this->authorize('admin');

        if ($order->product_type === OrderProductType::TrialBox) {
            return redirect()
                ->route('app.admin.orders.show', $order)
                ->with('status', __('admin.trial_order_box_setup.flash_redirected_from_compose'));
        }

        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Refunded) {
            return back()->with('status', 'order-not-composable');
        }

        $box = $order->box;
        if (! $box) {
            $box = Box::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'week_start_date' => ($order->scheduled_for_date ?? now())->copy()->startOfWeek()->toDateString(),
                'status' => BoxStatus::Locked,
                'modification_deadline_at' => now(),
                'estimated_value' => 0,
                'is_test' => (bool) $order->is_test,
            ]);
            $order->forceFill(['box_id' => $box->id])->save();
        }

        $composer->composePresetFromScratch($box, (string) $order->box_size);

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'order-composed');
    }

    public function markDelivered(Order $order): RedirectResponse
    {
        $this->authorize('admin');

        DB::transaction(function () use ($order) {
            $order->markDelivered();
            $order->deliveries()->update([
                'status' => DeliveryStatus::Delivered,
            ]);
        });

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'order-delivered');
    }

    private function resolveMondayForTrialSetup(Order $order, string $weekQuery): Carbon
    {
        if ($weekQuery !== '') {
            $candidate = Carbon::parse($weekQuery)->startOfDay();
            if ($candidate->isMonday()) {
                return $candidate;
            }
        }

        $boxWeek = $order->box?->week_start_date;
        if ($boxWeek !== null) {
            $d = Carbon::parse($boxWeek)->startOfDay();
            if ($d->isMonday()) {
                return $d;
            }
        }

        return WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
    }
}
