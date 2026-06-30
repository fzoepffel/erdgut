<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantStandingOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\RestaurantStandingOrder;
use App\Services\Restaurant\RestaurantStandingOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Restaurant-facing management of recurring deliveries (standing orders):
 * list, pause/resume, cancel. Creation happens implicitly when a catalog order
 * has weekly lines; editing is delete & recreate. All actions owner-gated.
 */
class RestaurantStandingOrderController extends Controller
{
    public function __construct(private readonly RestaurantStandingOrderService $standing) {}

    public function index(Request $request): View
    {
        $orders = RestaurantStandingOrder::query()
            ->where('user_id', $request->user()->id)
            ->whereNot('status', RestaurantStandingOrderStatus::Cancelled->value)
            ->with('items.product.translations')
            ->latest()
            ->get();

        return view('restaurant.standing-orders.index', ['standingOrders' => $orders]);
    }

    public function pause(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        abort_unless($standingOrder->user_id === $request->user()->id, 403);
        $this->standing->pause($standingOrder);

        return back()->with('status', 'standing-paused');
    }

    public function resume(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        abort_unless($standingOrder->user_id === $request->user()->id, 403);
        $this->standing->resume($standingOrder);

        return back()->with('status', 'standing-resumed');
    }

    public function destroy(Request $request, RestaurantStandingOrder $standingOrder): RedirectResponse
    {
        abort_unless($standingOrder->user_id === $request->user()->id, 403);
        $this->standing->cancel($standingOrder);

        return back()->with('status', 'standing-cancelled');
    }
}
