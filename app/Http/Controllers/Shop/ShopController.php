<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShopOrderRequest;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\ShopOrder;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ShopOrderService;
use App\Support\PickupLocations;
use App\Support\SumUpCheckoutLogger;
use Illuminate\Contracts\View\View;
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
    public function __construct(private readonly ShopOrderService $service) {}

    public function index(Request $request): View
    {
        $availableDates = $this->service->availableDates();

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

        $products = $selectedDate !== null
            ? $this->service->weeklyProductsForDate($selectedDate)
            : collect();

        return view('shop.index', [
            'availableDates' => $availableDates,
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

            return redirect()->route('public.shop.return', ['shopOrder' => $order->id]);
        }

        return redirect()->route('public.shop.pay', ['shopOrder' => $order->id]);
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
            'order' => $shopOrder->loadMissing('items.product.translations'),
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
