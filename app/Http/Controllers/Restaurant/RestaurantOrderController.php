<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\RestaurantOrderRequest;
use App\Mail\RestaurantOrderSubmittedMail;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
use App\Services\Shop\ShopClosureService;
use App\Support\AuditLogger;
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
                ->queue(new RestaurantOrderSubmittedMail($order));
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
    /**
     * "Nochmal bestellen" hands the order's lines into the SHOP basket
     * instead of creating an order instantly (a misclick used to place a
     * duplicate order). The shop's own checkout is the confirmation step:
     * quantities stay editable (hard-capped at today's stock), the delivery
     * day is picked there, and nothing exists until "Zahlungspflichtig
     * bestellen".
     */
    public function reorder(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);
        abort_if($restaurantOrder->isRequest(), 404);

        return redirect()->route('app.restaurant.catalog', ['reorder' => $restaurantOrder->id]);
    }

    public function show(Request $request, RestaurantOrder $restaurantOrder): View
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);

        $restaurantOrder->load(['items.product.translations', 'items.product.producer.translations']);

        // Change window: owner edits/cancel close when ordering for the
        // delivery day closes (shop cutoff).
        $closures = app(ShopClosureService::class);
        $isPendingRequest = $restaurantOrder->isRequest()
            && $restaurantOrder->status === RestaurantOrderStatus::Submitted;
        $locked = ! $isPendingRequest && $restaurantOrder->isLockedForChanges($closures);
        $date = $restaurantOrder->expected_delivery_date->copy();
        $dayState = $closures->statesForRange($date, $date)[$date->toDateString()] ?? null;

        return view('restaurant.orders.show', [
            'order' => $restaurantOrder,
            'locked' => $locked,
            'cutoffLabel' => $dayState['cutoff_label'] ?? null,
        ]);
    }

    /** Owner edit of a firm delivery: quantities / remove lines (until cutoff). */
    public function updateLines(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.remove' => ['nullable', 'boolean'],
        ]);

        $this->orders->ownerUpdateLines($restaurantOrder, $validated['lines']);

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $restaurantOrder->id,
            action: 'owner_adjust',
            fromState: $restaurantOrder->status->value,
            toState: $restaurantOrder->status->value,
            metadata: ['channel' => 'restaurant'],
            actorUserId: (int) $request->user()->id,
        );
        RestaurantOrderAdminNotifier::adjusted($restaurantOrder->refresh());

        return redirect()
            ->route('app.restaurant.orders.show', $restaurantOrder)
            ->with('status', 'restaurant-order-adjusted');
    }

    public function cancel(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_unless($restaurantOrder->user_id === $request->user()->id, 403);

        // A firm delivery can be cancelled while ordering for its delivery
        // day is still open (the shop cutoff = the change cutoff); after that
        // the goods are being sourced/packed. A still-pending request is not
        // a delivery yet and stays cancellable any time.
        $isPendingRequest = $restaurantOrder->isRequest()
            && $restaurantOrder->status === RestaurantOrderStatus::Submitted;
        $locked = ! $isPendingRequest
            && $restaurantOrder->isLockedForChanges(app(ShopClosureService::class));

        if ($locked) {
            return redirect()
                ->route('app.restaurant.orders.show', $restaurantOrder)
                ->with('status', 'restaurant-changes-locked');
        }

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
