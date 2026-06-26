<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutBasketRequest;
use App\Http\Requests\Shop\CheckoutDetailsRequest;
use App\Http\Requests\Shop\CheckoutPlaceRequest;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\ShopOrder;
use App\Services\Shop\CheckoutDraft;
use App\Services\Shop\CouponService;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ShopOrderService;
use App\Support\PickupLocations;
use App\Support\ShopOrderAdminNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Three-step storefront checkout: the customer first builds a basket on
 * /shop, then enters fulfillment + contact + payment details, then
 * reviews and confirms. State carries forward through a session-backed
 * {@see CheckoutDraft}; the final {@see self::place} action hands the
 * merged payload to {@see ShopOrderService::createOrder} — exactly the
 * same path the legacy one-shot {@see ShopController::store} uses, so
 * order shape, stock handling, coupons, and admin notifications stay
 * identical.
 *
 *   POST /shop/checkout/start    items + date → draft   → /shop/checkout/details
 *   GET  /shop/checkout/details  render details form
 *   POST /shop/checkout/details  details fields → draft → /shop/checkout/review
 *   GET  /shop/checkout/review   render review
 *   POST /shop/checkout/place    consent + create order → /shop/{order}/pay
 */
class ShopCheckoutController extends Controller
{
    public function __construct(
        private readonly ShopOrderService $service,
        private readonly CouponService $coupons,
        private readonly CheckoutDraft $draft,
    ) {}

    public function start(CheckoutBasketRequest $request): RedirectResponse
    {
        $items = (array) $request->validated('items');
        $date = (string) $request->validated('expected_delivery_date');
        $this->draft->setBasket($items, $date);

        return redirect()->route('public.shop.checkout.details');
    }

    public function details(): RedirectResponse|View
    {
        if (! $this->draft->hasBasket()) {
            return redirect()->route('public.shop.index');
        }

        $draft = $this->draft->current();

        if (! $this->dateStillAvailable((string) $draft['expected_delivery_date'])) {
            $this->draft->clear();

            return redirect()
                ->route('public.shop.index')
                ->with('error', __('shop.errors.checkout_date_unavailable'));
        }

        return view('shop.checkout.details', [
            'draft' => $draft,
            'lines' => $this->draftSummary($draft),
            'pickupLocations' => PickupLocations::all(),
            'deliveryFeeEur' => $this->service->deliveryFee(),
        ]);
    }

    public function storeDetails(CheckoutDetailsRequest $request): RedirectResponse
    {
        if (! $this->draft->hasBasket()) {
            return redirect()->route('public.shop.index');
        }

        $this->draft->setDetails((array) $request->validated());

        return redirect()->route('public.shop.checkout.review');
    }

    public function review(): RedirectResponse|View
    {
        if (! $this->draft->hasBasket()) {
            return redirect()->route('public.shop.index');
        }
        if (! $this->draft->hasDetails()) {
            return redirect()->route('public.shop.checkout.details');
        }

        $draft = $this->draft->current();

        if (! $this->dateStillAvailable((string) $draft['expected_delivery_date'])) {
            $this->draft->clear();

            return redirect()
                ->route('public.shop.index')
                ->with('error', __('shop.errors.checkout_date_unavailable'));
        }

        return view('shop.checkout.review', [
            'draft' => $draft,
            'lines' => $this->draftSummary($draft),
            'pickupLocations' => PickupLocations::all(),
            'deliveryFeeEur' => $this->service->deliveryFee(),
        ]);
    }

    public function place(CheckoutPlaceRequest $request): RedirectResponse
    {
        if (! $this->draft->hasBasket() || ! $this->draft->hasDetails()) {
            return redirect()->route('public.shop.index');
        }

        $draft = $this->draft->current();

        if (! $this->dateStillAvailable((string) $draft['expected_delivery_date'])) {
            $this->draft->clear();

            return redirect()
                ->route('public.shop.index')
                ->with('error', __('shop.errors.checkout_date_unavailable'));
        }

        // Customer hit Back from the SumUp widget straight onto the review
        // page and is now placing again — the previous attempt left an
        // orphan PendingPayment ShopOrder holding stock. Cancel it before
        // we try createOrder, or the very next availability check would
        // throw InsufficientStockException. Without this, the customer
        // sees the "out of stock" bounce once before a retry works.
        $this->cancelPreviousPlacedOrderIfPending();

        // Dedup the in-person back-button replay path the same way the
        // legacy POST /shop does (same email + date + window). Online
        // orders rely on the idempotent SumUp checkout URL instead.
        if (($draft['payment_method'] ?? 'online') === 'in_person') {
            $existing = ShopOrder::query()
                ->where('email', (string) ($draft['email'] ?? ''))
                ->whereDate('expected_delivery_date', (string) $draft['expected_delivery_date'])
                ->where('payment_method', 'in_person')
                ->where('status', ShopOrderStatus::PendingPayment)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest()
                ->first();
            if ($existing !== null) {
                $this->draft->clear();

                return redirect()->route('public.shop.return', ['shopOrder' => $existing->id]);
            }
        }

        try {
            $order = $this->service->createOrder($this->payloadFromDraft($draft));
        } catch (InsufficientStockException $e) {
            return redirect()
                ->route('public.shop.index', ['date' => (string) $draft['expected_delivery_date']])
                ->with('error', __('shop.errors.stock_vanished', ['name' => $e->product->name]));
        }

        if ($order->payment_method === 'in_person') {
            // In-person orders are finalized the moment the order row exists —
            // nothing to abandon, so it's safe to drop the draft right away.
            $this->draft->clear();
            $this->queueConfirmationMail($order);
            $this->coupons->finalizeRedemption($order);
            ShopOrderAdminNotifier::finalized($order);

            return redirect()->route('public.shop.return', ['shopOrder' => $order->id]);
        }

        // Online payment: the customer is about to land in the SumUp widget
        // and may walk away without paying. Keep the draft so their basket
        // survives a revisit to /shop, and remember the placed order id so
        // we can release its stock if they abandon.
        $this->draft->setPlacedOrderId($order->id);

        return redirect()->route('public.shop.pay', ['shopOrder' => $order->id]);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function payloadFromDraft(array $draft): array
    {
        return [
            'items' => array_values((array) ($draft['items'] ?? [])),
            'expected_delivery_date' => (string) ($draft['expected_delivery_date'] ?? ''),
            'fulfillment_mode' => (string) ($draft['fulfillment_mode'] ?? 'pickup'),
            'payment_method' => (string) ($draft['payment_method'] ?? 'online'),
            'pickup_location' => $draft['pickup_location'] ?? null,
            'name' => (string) ($draft['name'] ?? ''),
            'email' => (string) ($draft['email'] ?? ''),
            'phone' => $draft['phone'] ?? null,
            'delivery_street' => $draft['delivery_street'] ?? null,
            'delivery_postal_code' => $draft['delivery_postal_code'] ?? null,
            'delivery_city' => $draft['delivery_city'] ?? null,
            'delivery_notes' => $draft['delivery_notes'] ?? null,
            'coupon_code' => $draft['coupon_code'] ?? null,
        ];
    }

    /**
     * Compute a server-side summary of the draft basket — used by step 2 +
     * step 3 to show authoritative line totals + grand total (no client
     * estimate in the wizard pages; only /shop has the live stepper UI).
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function draftSummary(array $draft): array
    {
        $items = (array) ($draft['items'] ?? []);
        $mode = (string) ($draft['fulfillment_mode'] ?? 'pickup');
        $coupon = isset($draft['coupon_code']) ? (string) $draft['coupon_code'] : null;
        $email = isset($draft['email']) ? (string) $draft['email'] : null;
        $date = Carbon::parse((string) $draft['expected_delivery_date'])->startOfDay();

        return $this->service->quote($items, $mode, $coupon, $email, $date);
    }

    private function dateStillAvailable(string $date): bool
    {
        $menu = $this->service->availableDates()
            ->map(fn (array $row): string => $row['date']->toDateString())
            ->all();

        return in_array($date, $menu, true);
    }

    private function queueConfirmationMail(ShopOrder $order): void
    {
        // Mirrors ShopController::queueConfirmationMail. Pulling the
        // implementation back here would couple two controllers; the
        // mailable + service are stateless, so the duplication is cheap.
        //
        // A failed send must never break checkout: the order row is already
        // committed by the time we get here, so letting a mail/SMTP error
        // bubble up would 500 the customer (who may then re-order) for an
        // order that actually exists. On hosts using the `sync` queue driver
        // `->queue()` sends inline, so a transport failure (e.g. bad SMTP
        // credentials) surfaces right here — swallow and log it, exactly as
        // ShopController::queueConfirmationMail already does.
        try {
            Mail::to($order->email)
                ->locale(app()->getLocale())
                ->queue(new ShopOrderConfirmationMail($order));
        } catch (Throwable $e) {
            report($e);
            Log::warning('shop.confirmation_mail.queue_failed', [
                'shop_order_id' => $order->id,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel the customer's previously-placed PendingPayment order, if any,
     * so its stock is back in circulation before we try to create a new one.
     * Used by {@see self::place} on a retry; also runs in
     * {@see ShopController::index} when the customer revisits /shop. The
     * draft's placed_order_id marker is dropped either way.
     */
    private function cancelPreviousPlacedOrderIfPending(): void
    {
        $placedOrderId = $this->draft->placedOrderId();
        if ($placedOrderId === null) {
            return;
        }
        $stale = ShopOrder::query()->find($placedOrderId);
        if ($stale !== null && $stale->status === ShopOrderStatus::PendingPayment) {
            $stale->status = ShopOrderStatus::Cancelled;
            $stale->save();
        }
        $this->draft->forgetPlacedOrderId();
    }
}
