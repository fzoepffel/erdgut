<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantStandingOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStandingOrderEndRequest;
use App\Http\Requests\Admin\AdminStandingOrderItemsRequest;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Restaurant\RestaurantStandingOrderService;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin management of ALL recurring B2B deliveries (Lieferpläne): template
 * quantities, end date, pause/resume, activate, cancel. Mirrors the
 * restaurant-side controller without the owner gate; every safety rule
 * (hand-adjusted weeks survive, invoiced rows never deleted, closed dates
 * skipped) lives in the service and applies here too.
 *
 * No mails are sent from these actions: the admin usually acts on the
 * restaurant's phoned request, and the service deliberately stays silent on
 * pause/cancel/trim. If notifications become desired, they slot in here.
 */
class AdminStandingOrderController extends Controller
{
    public function __construct(private readonly RestaurantStandingOrderService $standing) {}

    public function index(): View
    {
        $orders = RestaurantStandingOrder::query()
            ->with(['items.product.translations', 'user.restaurantProfile'])
            ->orderByRaw("CASE status
                WHEN 'pending' THEN 0
                WHEN 'active' THEN 1
                WHEN 'paused' THEN 2
                ELSE 3 END")
            ->latest()
            ->get();

        $nextDeliveries = RestaurantOrder::query()
            ->whereIn('source_standing_order_id', $orders->pluck('id'))
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->whereDate('expected_delivery_date', '>=', today()->toDateString())
            ->groupBy('source_standing_order_id')
            ->selectRaw('source_standing_order_id, MIN(expected_delivery_date) as next_date')
            ->pluck('next_date', 'source_standing_order_id');

        return view('admin.standing-orders.index', [
            'standingOrders' => $orders,
            'nextDeliveries' => $nextDeliveries,
        ]);
    }

    public function activate(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        $this->standing->activate($standingOrder, (int) $request->user()->id);
        $this->audit($request, $standingOrder, 'admin_activate', RestaurantStandingOrderStatus::Pending->value);

        return back()->with('status', 'standing-activated');
    }

    public function pause(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        $from = $standingOrder->status->value;
        $this->standing->pause($standingOrder);
        $this->audit($request, $standingOrder, 'admin_pause', $from);

        return back()->with('status', 'standing-paused');
    }

    public function resume(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        $from = $standingOrder->status->value;
        $this->standing->resume($standingOrder);
        $this->audit($request, $standingOrder, 'admin_resume', $from);

        return back()->with('status', 'standing-resumed');
    }

    public function setEnd(AdminStandingOrderEndRequest $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        $standingOrder->items()->update(['ends_on' => $request->validated('ends_on')]);
        $this->standing->applyEndChange($standingOrder->refresh());
        $this->audit($request, $standingOrder, 'admin_set_end', $standingOrder->status->value, [
            'ends_on' => $request->validated('ends_on'),
        ]);

        return back()->with('status', 'standing-end-updated');
    }

    public function updateItems(AdminStandingOrderItemsRequest $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        $this->standing->updateItems($standingOrder, $request->validated('lines'));
        $this->audit($request, $standingOrder, 'admin_update_items', $standingOrder->status->value, [
            'lines' => $request->validated('lines'),
        ]);

        return back()->with('status', 'standing-items-updated');
    }

    public function destroy(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        $from = $standingOrder->status->value;
        $this->standing->cancel($standingOrder);
        $this->audit($request, $standingOrder, 'admin_cancel', $from);

        return back()->with('status', 'standing-cancelled');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(Request $request, RestaurantStandingOrder $standingOrder, string $action, string $fromState, array $metadata = []): void
    {
        AuditLogger::log(
            entityType: 'restaurant_standing_order',
            entityId: (int) $standingOrder->id,
            action: $action,
            fromState: $fromState,
            toState: $standingOrder->refresh()->status->value,
            metadata: array_merge(['channel' => 'admin'], $metadata),
            actorUserId: (int) $request->user()->id,
        );
    }
}
