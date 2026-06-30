<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPriceRestaurantOrderRequest;
use App\Mail\RestaurantOrderConfirmedMail;
use App\Mail\RestaurantOrderDeclinedMail;
use App\Mail\RestaurantOrderInvoicedMail;
use App\Models\RestaurantOrder;
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

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $status = RestaurantOrderStatus::tryFrom((string) $request->query('status', ''));

        $orders = RestaurantOrder::query()
            ->with('user')
            ->withCount('items')
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            // Submitted (awaiting action) first, then newest.
            ->orderByRaw("CASE WHEN status = '".RestaurantOrderStatus::Submitted->value."' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.restaurant-orders.index', [
            'orders' => $orders,
            'statuses' => RestaurantOrderStatus::cases(),
            'activeStatus' => $status,
        ]);
    }

    public function show(RestaurantOrder $restaurantOrder): View
    {
        $this->authorize('admin');

        $restaurantOrder->load(['user.restaurantProfile', 'items.product.translations', 'items.product.producer.translations']);

        return view('admin.restaurant-orders.show', ['order' => $restaurantOrder]);
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
                ->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // --- Invoice (R6) ---------------------------------------------------

    public function updatePricing(AdminPriceRestaurantOrderRequest $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_if($restaurantOrder->isInvoiced(), 403);
        abort_unless($restaurantOrder->isInvoiceable(), 403);

        $this->invoices->updatePricing($restaurantOrder, $request->validated('items'));

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_priced_flash'));
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
                    ->send(new RestaurantOrderInvoicedMail($restaurantOrder));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_issued_flash', ['number' => $restaurantOrder->invoice_number]));
    }

    public function invoicePdf(RestaurantOrder $restaurantOrder): SymfonyResponse
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 404);

        return $this->invoices->renderPdf($restaurantOrder)
            ->download('erdgut-rechnung-'.$restaurantOrder->invoice_number.'.pdf');
    }
}
