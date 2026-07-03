<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantOrderLineStatus;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopStockAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\RestaurantOrderRequest;
use App\Mail\RestaurantOrderSubmittedMail;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Admin\InventoryService;
use App\Services\Restaurant\RestaurantCatalogService;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
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
        // Shop orders only — requests live in the Anfragen area. Future
        // auto-generated weekly deliveries collapse to the next occurrence;
        // the further weeks are summarized per standing order below.
        $orders = RestaurantOrder::query()
            ->where('user_id', $request->user()->id)
            ->orders()
            ->collapseFutureRecurring()
            ->withCount('items')
            ->with('items.product.translations')
            ->latest()
            ->paginate(20);

        // One summary line per active standing order with further planned weeks.
        $recurringSummaries = RestaurantStandingOrder::query()
            ->where('user_id', $request->user()->id)
            ->active()
            ->get()
            ->map(function ($standing) {
                $future = RestaurantOrder::query()
                    ->where('source_standing_order_id', $standing->id)
                    ->where('status', RestaurantOrderStatus::Confirmed->value)
                    ->whereDate('expected_delivery_date', '>=', today()->toDateString())
                    ->orderBy('expected_delivery_date')
                    ->pluck('expected_delivery_date');

                return $future->count() > 1 ? [
                    'standing' => $standing,
                    'count' => $future->count(),
                    'next' => $future->first(),
                    'until' => $future->last(),
                ] : null;
            })
            ->filter()
            ->values();

        return view('restaurant.orders.index', [
            'orders' => $orders,
            'recurringSummaries' => $recurringSummaries,
        ]);
    }

    /** All issued invoices of this restaurant — bookkeeping view. */
    public function invoices(Request $request): View
    {
        $invoices = RestaurantOrder::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('invoice_number')
            ->orderByDesc('invoiced_at')
            ->paginate(20);

        return view('restaurant.invoices.index', ['invoices' => $invoices]);
    }

    public function store(RestaurantOrderRequest $request, RestaurantStandingOrderService $standing): RedirectResponse
    {
        $items = $request->validated('items');

        // Shop checkout: in-stock catalog lines only — over-stock or free-form
        // lines are a 422 (they belong in the Anfragen area).
        $order = $this->orders->submitOrder($request->user(), [
            'items' => $items,
            'expected_delivery_date' => $request->validated('expected_delivery_date'),
            'delivery_street' => $request->validated('delivery_street'),
            'delivery_postal_code' => $request->validated('delivery_postal_code'),
            'delivery_city' => $request->validated('delivery_city'),
            'delivery_notes' => $request->validated('delivery_notes'),
            'locale' => app()->getLocale(),
        ]);

        // Weekly lines become a recurring standing order (Pending until the
        // admin confirms this first order). One-time lines stay on this order only.
        $weeklyLines = array_values(array_filter(
            $items,
            fn ($line) => ($line['recurrence'] ?? 'one_time') === 'weekly',
        ));
        if ($weeklyLines !== []) {
            $standingOrder = $standing->createFromOrder($order, $weeklyLines);
            // If the first order auto-confirmed (all weekly lines were in stock),
            // activate the recurring delivery now — the admin-confirm path that
            // normally activates it (RestaurantOrderService::confirm) never runs.
            if ($standingOrder !== null
                && $order->status === RestaurantOrderStatus::Confirmed
                && $standingOrder->isPending()) {
                $standing->activate($standingOrder, null);
            }
        }

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

    /**
     * One-click reorder: copy a previous shop order's lines into a NEW order
     * for the next open delivery day. Each line is clamped to today's stock
     * (dropped when nothing remains) so the reorder is always a firm, fully
     * reserved order; a flash lists what was adjusted. Orders only.
     */
    public function reorder(Request $request, RestaurantOrder $restaurantOrder, RestaurantCatalogService $catalog): RedirectResponse
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);
        abort_if($restaurantOrder->isRequest(), 404);

        $nextDay = $catalog->selectableDates()->first();
        abort_unless($nextDay !== null, 422);

        $inventory = app(InventoryService::class);
        $date = $nextDay['date'];
        $adjusted = [];
        $items = [];

        $sourceItems = $restaurantOrder->items()
            ->whereNotNull('product_id')
            ->where('line_status', '!=', RestaurantOrderLineStatus::Declined->value)
            ->with('product')
            ->get();
        foreach ($sourceItems as $item) {
            if ($item->product === null) {
                continue;
            }
            $remaining = $inventory->availableOnDate($item->product, $date, ShopStockAudience::BusinessTrade, $item->variant_id ?? 0);
            $qty = min((float) $item->quantity, max(0.0, $remaining));
            if ($qty <= 0) {
                $adjusted[] = $item->displayName();

                continue;
            }
            if ($qty < (float) $item->quantity) {
                $adjusted[] = $item->displayName();
            }
            $items[] = ['product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'quantity' => $qty];
        }

        if ($items === []) {
            return redirect()
                ->route('app.restaurant.orders.show', $restaurantOrder)
                ->with('status', 'restaurant-reorder-empty');
        }

        $order = $this->orders->submitOrder($request->user(), [
            'items' => $items,
            'expected_delivery_date' => $nextDay['date']->toDateString(),
            'delivery_street' => $restaurantOrder->delivery_street,
            'delivery_postal_code' => $restaurantOrder->delivery_postal_code,
            'delivery_city' => $restaurantOrder->delivery_city,
            'delivery_notes' => $restaurantOrder->delivery_notes,
            'locale' => app()->getLocale(),
        ]);

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
            ->with('status', 'restaurant-order-submitted')
            ->with('reorder_adjusted', $adjusted);
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

        // The restaurant may cancel its own order any time before it's delivered
        // or invoiced — including an auto-confirmed in-stock order. Cancelling
        // releases the reserved stock (status leaves the reserving set).
        $cancellable = in_array($restaurantOrder->status, [
            RestaurantOrderStatus::Submitted,
            RestaurantOrderStatus::Confirmed,
        ], true) && ! $restaurantOrder->isInvoiced();

        if ($cancellable) {
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
