<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPriceRestaurantOrderRequest;
use App\Mail\RestaurantOrderCancelledMail;
use App\Mail\RestaurantOrderConfirmedMail;
use App\Mail\RestaurantOrderDeclinedMail;
use App\Mail\RestaurantOrderInvoicedMail;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin queue + management of restaurant (B2B) requests. Order-level only:
 * confirm / decline / fulfil the whole request (per-line edits + pricing are
 * the invoice step). Declining releases the stock hold automatically.
 */
class AdminRestaurantOrderController extends Controller
{
    public function __construct(
        private readonly RestaurantOrderService $orders,
        private readonly RestaurantInvoiceService $invoices,
    ) {}

    /**
     * The Anfragen queue: REQUESTS only. Firm restaurant orders (including
     * granted requests) live in the unified shop-orders overview — this page
     * is where pending requests wait for per-line accept/decline.
     */
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $status = RestaurantOrderStatus::tryFrom((string) $request->query('status', ''));

        $orders = RestaurantOrder::query()
            ->requests()
            ->with('user')
            ->withCount('items')
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            // Submitted (awaiting action) first, then newest.
            ->orderByRaw("CASE WHEN status = '".RestaurantOrderStatus::Submitted->value."' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // Open requests need admin action — surface the count in the heading.
        $openRequests = RestaurantOrder::query()
            ->requests()
            ->where('status', RestaurantOrderStatus::Submitted->value)
            ->count();

        return view('admin.restaurant-orders.index', [
            'orders' => $orders,
            'statuses' => RestaurantOrderStatus::cases(),
            'activeStatus' => $status,
            'openRequests' => $openRequests,
        ]);
    }

    public function show(RestaurantOrder $restaurantOrder): View
    {
        $this->authorize('admin');

        $restaurantOrder->load(['user.restaurantProfile', 'standingOrder', 'items.product.translations', 'items.product.producer.translations']);

        return view('admin.restaurant-orders.show', [
            'order' => $restaurantOrder,
            // Sequence preview so the operator can align the counter with
            // manually issued invoices before the first system invoice.
            'nextInvoiceNumber' => $restaurantOrder->isInvoiceable()
                ? $this->invoices->nextInvoiceNumberPreview()
                : null,
        ]);
    }

    public function confirm(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        return $this->act($request, $restaurantOrder, 'confirm');
    }

    public function decline(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        return $this->act($request, $restaurantOrder, 'decline');
    }

    public function fulfil(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        return $this->act($request, $restaurantOrder, 'fulfil');
    }

    /** Accept a single on-request/special line. Order settles to Confirmed once none pend. */
    public function acceptLine(Request $request, RestaurantOrder $restaurantOrder, RestaurantOrderItem $item): RedirectResponse
    {
        return $this->actOnLine($request, $restaurantOrder, $item, 'accept');
    }

    /** Decline a single on-request/special line. */
    public function declineLine(Request $request, RestaurantOrder $restaurantOrder, RestaurantOrderItem $item): RedirectResponse
    {
        return $this->actOnLine($request, $restaurantOrder, $item, 'decline');
    }

    private function actOnLine(Request $request, RestaurantOrder $order, RestaurantOrderItem $item, string $action): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless($item->restaurant_order_id === $order->id, 404);

        $from = $order->status->value;
        $applied = $action === 'accept'
            ? $this->orders->acceptLine($item)
            : $this->orders->declineLine($item);

        if ($applied) {
            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $order->id,
                action: $action.'_line',
                fromState: $from,
                toState: $order->fresh()->status->value,
                metadata: ['channel' => 'admin', 'item_id' => (int) $item->id],
                actorUserId: (int) $request->user()->id,
            );

            // If resolving this line settled the whole order, tell the restaurant.
            $order->refresh();
            if ($from === RestaurantOrderStatus::Submitted->value) {
                if ($order->status === RestaurantOrderStatus::Confirmed) {
                    $this->notify($order, 'confirm', null);
                } elseif ($order->status === RestaurantOrderStatus::Declined) {
                    $this->notify($order, 'decline', null);
                }
            }
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $order)
            ->with('status', __('restaurant.admin_line_flash_'.$action));
    }

    /** Cancel an order before delivery/invoice (releases the reservation). */
    public function cancel(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        $note = trim((string) $request->input('note')) ?: null;
        $from = $restaurantOrder->status->value;

        if ($this->orders->adminCancel($restaurantOrder)) {
            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $restaurantOrder->id,
                action: 'cancel',
                fromState: $from,
                toState: $restaurantOrder->status->value,
                reason: $note,
                metadata: ['channel' => 'admin'],
                actorUserId: (int) $request->user()->id,
            );

            if ($restaurantOrder->user !== null) {
                try {
                    Mail::to($restaurantOrder->user->email)
                        ->locale($restaurantOrder->locale ?? app()->getLocale())
                        ->queue(new RestaurantOrderCancelledMail($restaurantOrder, $note));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_orders_flash_cancel', ['id' => $restaurantOrder->id]));
    }

    private function act(Request $request, RestaurantOrder $order, string $action): RedirectResponse
    {
        $this->authorize('admin');

        $note = trim((string) $request->input('note')) ?: null;
        $from = $order->status->value;

        $applied = match ($action) {
            'confirm' => $this->orders->confirm($order),
            'decline' => $this->orders->decline($order),
            'fulfil' => $this->orders->fulfil($order),
            default => false,
        };

        if ($applied) {
            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $order->id,
                action: $action,
                fromState: $from,
                toState: $order->status->value,
                reason: $note,
                metadata: ['channel' => 'admin'],
                actorUserId: (int) $request->user()->id,
            );

            $this->notify($order, $action, $note);
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $order)
            ->with('status', __('restaurant.admin_orders_flash_'.$action, ['id' => $order->id]));
    }

    private function notify(RestaurantOrder $order, string $action, ?string $note): void
    {
        $mailable = match ($action) {
            'confirm' => new RestaurantOrderConfirmedMail($order, $note),
            'decline' => new RestaurantOrderDeclinedMail($order, $note),
            default => null, // fulfil is internal — no restaurant email
        };

        if (! $mailable instanceof Mailable || $order->user === null) {
            return;
        }

        try {
            Mail::to($order->user->email)
                ->locale($order->locale ?? app()->getLocale())
                ->queue($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // --- Invoice (R6) ---------------------------------------------------

    public function updatePricing(AdminPriceRestaurantOrderRequest $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_if($restaurantOrder->isInvoiced(), 403);
        abort_unless($restaurantOrder->isInvoiceable(), 403);

        $this->invoices->updatePricing(
            $restaurantOrder,
            $request->validated('items'),
            $request->filled('delivery_fee_net_eur') ? (float) $request->validated('delivery_fee_net_eur') : null,
            $request->filled('delivery_fee_vat_rate_percent') ? (int) $request->validated('delivery_fee_vat_rate_percent') : null,
        );

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_priced_flash'));
    }

    /**
     * Align the yearly invoice-number sequence so the NEXT invoice gets the
     * entered number. Needed because invoices were issued manually before the
     * system took over and the counter must continue, not restart.
     */
    public function setInvoiceSequence(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'next_invoice_number' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $this->invoices->setNextSequenceNumber((int) $validated['next_invoice_number']);

        AuditLogger::log(
            entityType: 'number_sequence',
            entityId: (int) now()->year,
            action: 'set_invoice_sequence',
            fromState: null,
            toState: (string) $validated['next_invoice_number'],
            metadata: ['channel' => 'admin', 'next_number' => $this->invoices->nextInvoiceNumberPreview()],
            actorUserId: (int) $request->user()->id,
        );

        return back()->with('status', __('restaurant.admin_invoice_sequence_set_flash', [
            'number' => $this->invoices->nextInvoiceNumberPreview(),
        ]));
    }

    public function issueInvoice(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        abort_if($restaurantOrder->isInvoiced(), 403);
        abort_unless($restaurantOrder->isInvoiceable(), 403);

        try {
            $this->invoices->issueInvoice($restaurantOrder);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('app.admin.restaurant-orders.show', $restaurantOrder)
                ->with('status', $e->getMessage());
        }

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $restaurantOrder->id,
            action: 'invoice',
            fromState: $restaurantOrder->status->value,
            toState: $restaurantOrder->status->value,
            metadata: ['channel' => 'admin', 'invoice_number' => $restaurantOrder->invoice_number],
            actorUserId: (int) $request->user()->id,
        );

        if ($restaurantOrder->user !== null) {
            try {
                Mail::to($restaurantOrder->user->email)
                    ->locale($restaurantOrder->locale ?? app()->getLocale())
                    ->queue(new RestaurantOrderInvoicedMail($restaurantOrder));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_issued_flash', ['number' => $restaurantOrder->invoice_number]));
    }

    /** Mark an issued invoice as paid (bank transfer arrived). Idempotent. */
    public function markPaid(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 403);

        if (! $restaurantOrder->isInvoicePaid()) {
            $restaurantOrder->forceFill(['invoice_paid_at' => now()])->save();

            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $restaurantOrder->id,
                action: 'invoice_paid',
                fromState: $restaurantOrder->status->value,
                toState: $restaurantOrder->status->value,
                metadata: ['channel' => 'admin', 'invoice_number' => $restaurantOrder->invoice_number],
                actorUserId: (int) $request->user()->id,
            );
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_paid_flash', ['number' => $restaurantOrder->invoice_number]));
    }

    public function invoicePdf(RestaurantOrder $restaurantOrder): SymfonyResponse
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 404);

        return $this->invoices->renderPdf($restaurantOrder)
            ->download('erdgut-rechnung-'.$restaurantOrder->invoice_number.'.pdf');
    }
}
