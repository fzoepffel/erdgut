<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\RestaurantOrderRequest;
use App\Mail\RestaurantOrderSubmittedMail;
use App\Models\RestaurantOrder;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Support\RestaurantOrderAdminNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Restaurant (B2B) order/request submission + "my requests" area. All actions
 * sit behind the `restaurant` (verified) middleware; show/cancel are owner-gated.
 */
class RestaurantOrderController extends Controller
{
    public function __construct(private readonly RestaurantOrderService $orders) {}

    public function index(Request $request): View
    {
        $orders = RestaurantOrder::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->paginate(20);

        return view('restaurant.orders.index', ['orders' => $orders]);
    }

    public function store(RestaurantOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->submit($request->user(), [
            'items' => $request->validated('items'),
            'expected_delivery_date' => $request->validated('expected_delivery_date'),
            'delivery_street' => $request->validated('delivery_street'),
            'delivery_postal_code' => $request->validated('delivery_postal_code'),
            'delivery_city' => $request->validated('delivery_city'),
            'delivery_notes' => $request->validated('delivery_notes'),
            'locale' => app()->getLocale(),
        ]);

        // Confirmation to the restaurant + admin notification. Both are
        // failure-tolerant: staging runs the sync queue, so a mail error must
        // not 500 the submission.
        try {
            Mail::to($request->user()->email)
                ->locale($order->locale ?? app()->getLocale())
                ->send(new RestaurantOrderSubmittedMail($order));
        } catch (\Throwable $e) {
            report($e);
        }
        RestaurantOrderAdminNotifier::submitted($order);

        return redirect()
            ->route('app.restaurant.orders.show', $order)
            ->with('status', 'restaurant-order-submitted');
    }

    public function show(Request $request, RestaurantOrder $restaurantOrder): View
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);

        $restaurantOrder->load(['items.product.translations', 'items.product.producer.translations']);

        return view('restaurant.orders.show', ['order' => $restaurantOrder]);
    }

    public function cancel(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);

        if ($restaurantOrder->status === RestaurantOrderStatus::Submitted) {
            $this->orders->cancel($restaurantOrder);
        }

        return redirect()
            ->route('app.restaurant.orders.show', $restaurantOrder)
            ->with('status', 'restaurant-order-cancelled');
    }

    public function invoicePdf(Request $request, RestaurantOrder $restaurantOrder, RestaurantInvoiceService $invoices): SymfonyResponse
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);
        abort_unless($restaurantOrder->isInvoiced(), 404);

        return $invoices->renderPdf($restaurantOrder)
            ->download('erdgut-rechnung-'.$restaurantOrder->invoice_number.'.pdf');
    }
}
