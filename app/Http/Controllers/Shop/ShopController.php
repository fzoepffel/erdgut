<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShopOrderRequest;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\ShopOrder;
use App\Services\Shop\CouponService;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ShopOrderService;
use App\Support\PickupLocations;
use App\Support\ShopOrderAdminNotifier;
use App\Support\SumUpCheckoutLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Public storefront for the anonymous, multi-product general shop.
 *
 * The admin-managed successor to the hard-coded spargel channel. Each
 * visit is one anonymous purchase against a {@see ShopOrder} (no auth,
 * no User / Order / Subscription linkage).
 *
 *   GET  /shop                 storefront — date picker + available products
 *   POST /shop                 validate + create order → /shop/{order}/pay
 *   GET  /shop/{order}/pay     initiate SumUp checkout + render widget
 *   GET  /shop/{order}/return  SumUp redirect target — verify + thank-you
 */
class ShopController extends Controller
{
    public function __construct(
        private readonly ShopOrderService $service,
        private readonly CouponService $coupons,
    ) {}

    public function index(Request $request): View
    {
        $index = $this->service->availabilityIndex();
        $availableDates = $index['dates'];
        $filterProducts = $index['products'];

        // Resolve the active date: the `?date=` query param if it's still
        // on the menu, else the first available date. Null when the shop
        // has no stock at all (empty-state view).
        $selectedDate = null;
        $requested = (string) $request->query('date', '');
        $menu = $availableDates->map(fn (array $row): string => $row['date']->toDateString())->all();

        if ($requested !== '' && in_array($requested, $menu, true)) {
            $selectedDate = Carbon::parse($requested)->startOfDay();
        } elseif ($availableDates->isNotEmpty()) {
            $selectedDate = $availableDates->first()['date'];
        }

        // Resolve the active product filter from `?product=` — must be a
        // known catalog product ID; unknown/invalid values are silently dropped.
        $activeProductId = (int) $request->query('product', 0);
        if ($activeProductId !== 0 && ! $filterProducts->contains('id', $activeProductId)) {
            $activeProductId = 0;
        }

        $products = $selectedDate !== null
            ? $this->service->weeklyProductsForDate($selectedDate)
            : collect();

        return view('shop.index', [
            'availableDates' => $availableDates,
            'filterProducts' => $filterProducts,
            'activeProductId' => $activeProductId,
            'selectedDate' => $selectedDate,
            'products' => $products,
            'pickupLocations' => PickupLocations::all(),
            'deliveryFeeEur' => $this->service->deliveryFee(),
            'vatRatePercent' => (int) config('shop.vat_rate_percent', 7),
            'quantityStep' => (int) config('shop.quantity_step', 50),
            'maxQuantity' => (int) config('shop.max_quantity', 3000),
            'maxCount' => (int) config('shop.max_count', 12),
        ]);
    }

    public function store(ShopOrderRequest $request): RedirectResponse
    {
        try {
            $order = $this->service->createOrder($request->validated());
        } catch (InsufficientStockException $e) {
            // Stock vanished between form render and submit (a concurrent
            // order cleared it). Bounce back with a localized message
            // naming the product to reduce; keep the chosen date.
            return redirect()
                ->route('public.shop.index', ['date' => (string) $request->input('expected_delivery_date')])
                ->withInput()
                ->with('error', __('shop.errors.stock_vanished', ['name' => $e->product->name]));
        }

        if ($order->payment_method === 'in_person') {
            $this->queueConfirmationMail($order);
            // In-person checkout is a confirmed sale right away — record the
            // coupon redemption and notify admins now (the online half fires
            // from the observer once SumUp confirms).
            $this->coupons->finalizeRedemption($order);
            ShopOrderAdminNotifier::finalized($order);

            return redirect()->route('public.shop.return', ['shopOrder' => $order->id]);
        }

        return redirect()->route('public.shop.pay', ['shopOrder' => $order->id]);
    }

    /**
     * Re-price the cart server-side with a typed coupon code and return the
     * authoritative discount + new total as JSON for the live preview. Never
     * trusts a client-computed discount; runs the same
     * {@see ShopOrderService::quote()} the order create + SumUp charge use.
     * Rejection reasons are mapped to deliberately-coarse localized messages
     * so the endpoint can't be used to enumerate which codes exist.
     */
    public function couponPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'fulfillment_mode' => ['required', 'in:pickup,delivery'],
        ]);

        // Normalize the storefront's quantity map into clean cart lines.
        $items = [];
        foreach ((array) $request->input('quantities', []) as $productId => $quantity) {
            $qty = (float) $quantity;
            if ($qty > 0) {
                $items[] = ['product_id' => (int) $productId, 'quantity' => $qty];
            }
        }

        $quote = $this->service->quote(
            $items,
            (string) $data['fulfillment_mode'],
            $data['coupon_code'] ?? null,
            $data['email'] ?? null,
        );

        $applied = $quote['coupon'] !== null;
        $message = null;
        if (! $applied && $quote['coupon_error'] !== null) {
            $message = __('coupon.errors.'.$quote['coupon_error'], $quote['coupon_error_params']);
        }

        return response()->json([
            'applied' => $applied,
            'code' => $quote['coupon_code'],
            'type' => $quote['coupon']?->type->value,
            'items_total_eur' => $quote['items_total_eur'],
            'delivery_fee_eur' => $quote['delivery_fee_eur'],
            'discount_eur' => $quote['discount_eur'],
            'total_amount_eur' => $quote['total_amount_eur'],
            'message' => $message,
        ]);
    }

    public function pay(Request $request, ShopOrder $shopOrder): RedirectResponse|View
    {
        if ($shopOrder->payment_method === 'in_person') {
            return redirect()->route('public.shop.return', ['shopOrder' => $shopOrder->id]);
        }

        // Retry path: a previous attempt failed. Reset to PendingPayment +
        // clear the stale checkout id so /pay creates a fresh checkout.
        if ($shopOrder->status === ShopOrderStatus::Failed) {
            $shopOrder->forceFill([
                'status' => ShopOrderStatus::PendingPayment,
                'sumup_checkout_id' => null,
                'sumup_payment_id' => null,
            ])->save();
            $shopOrder->refresh();
        }

        if ($shopOrder->status !== ShopOrderStatus::PendingPayment) {
            return redirect()->route('public.shop.return', ['shopOrder' => $shopOrder->id]);
        }

        $returnUrl = route('public.shop.return', ['shopOrder' => $shopOrder->id], absolute: true);

        $checkoutId = null;
        $checkoutError = null;

        // Reuse an existing checkout on page reloads / back-navigation so we
        // never orphan the checkout the SumUp widget already loaded.
        if ($shopOrder->sumup_checkout_id !== null) {
            $checkoutId = $shopOrder->sumup_checkout_id;
        } else {
            try {
                $checkout = $this->service->initiateCheckout($shopOrder, $returnUrl);
                $checkoutId = (string) (data_get($checkout, 'id') ?? '') ?: null;

                if ($checkoutId === null) {
                    $checkoutError = 'no_checkout_id';
                    SumUpCheckoutLogger::warning('Shop pay: SumUp returned no checkout id.', [
                        'shop_order_id' => $shopOrder->id,
                    ]);
                }
            } catch (Throwable $e) {
                report($e);
                $checkoutError = 'checkout_creation_failed';
                SumUpCheckoutLogger::warning('Shop pay: SumUp checkout creation failed.', [
                    'shop_order_id' => $shopOrder->id,
                    'exception_class' => $e::class,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                ]);
            }
        }

        return view('shop.pay', [
            'order' => $shopOrder,
            'checkoutId' => $checkoutId,
            'checkoutError' => $checkoutError,
            'returnUrl' => $returnUrl,
            'vatRatePercent' => (int) config('shop.vat_rate_percent', 7),
        ]);
    }

    public function return(Request $request, ShopOrder $shopOrder): View
    {
        // Authoritative server-side verification — never trust the
        // redirect. Skip for in-person orders (never charged via SumUp).
        if ($shopOrder->status === ShopOrderStatus::PendingPayment && $shopOrder->payment_method !== 'in_person') {
            $outcome = $this->service->verifyPaymentWithProvider($shopOrder);
            $shopOrder->refresh();

            if ($outcome === ShopOrderService::VERIFY_PAID) {
                $this->queueConfirmationMail($shopOrder);
            }
        }

        return view('shop.return', [
            'order' => $shopOrder->loadMissing([
                'items.product.translations',
                'items.product.producer.translations',
            ]),
        ]);
    }

    /**
     * Stable per-order tracking page: mirrors the confirmation details and
     * surfaces the live delivery / pickup window + contact line. Guarded by
     * a signed URL so the page can't be reached by guessing order ids.
     */
    public function track(Request $request, ShopOrder $shopOrder): View
    {
        abort_unless($request->hasValidSignature(), 403);

        return view('shop.track', [
            'order' => $shopOrder->loadMissing([
                'items.product.translations',
                'items.product.producer.translations',
            ]),
        ]);
    }

    /**
     * Queue the shop confirmation email. Swallows mail-driver failures so
     * a transient outage never breaks the order flow itself.
     */
    private function queueConfirmationMail(ShopOrder $order): void
    {
        try {
            Mail::to($order->email)->queue(new ShopOrderConfirmationMail($order));
        } catch (Throwable $e) {
            report($e);
            SumUpCheckoutLogger::warning('Shop order: failed to queue confirmation email.', [
                'shop_order_id' => $order->id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }
    }
}
