<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShopOrderRequest;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\BoxWaitlistSignup;
use App\Models\Product;
use App\Models\ProductEngagementDaily;
use App\Models\ProductSubcategory;
use App\Models\ShopOrder;
use App\Services\Shop\CouponService;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ProductRankingService;
use App\Services\Shop\ShopOrderService;
use App\Support\PickupLocations;
use App\Support\ShopOrderAdminNotifier;
use App\Support\SumUpCheckoutLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
        private readonly ProductRankingService $ranking,
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

        // Resolve the active product filter from `?product[]=` — array of known
        // catalog product IDs; unknown/invalid values are silently dropped.
        $activeProductIds = array_values(array_filter(
            array_map('intval', (array) $request->query('product', [])),
            fn (int $id): bool => $id > 0 && $filterProducts->contains('id', $id),
        ));

        $products = $selectedDate !== null
            ? $this->service->weeklyProductsForDate($selectedDate)
            : collect();

        // Sales-driving order: a margin-per-impression Thompson-sampling bandit
        // ranks orderable products (exploit winners + explore new ones), seeded
        // per session+day so the order is stable within a visit but rotates
        // across visits. See ProductRankingService.
        if ($products->isNotEmpty()) {
            $products = $this->ranking->orderForStorefront($products, $this->storefrontSeed($request));
        }

        // Categories that have at least one product available on this day —
        // used to render only relevant category filter chips.
        $availableCategories = $products
            ->map(fn (array $row): string => $row['product']->category)
            ->unique()
            ->values()
            ->all();

        // When the selected day only has one category, pre-activate it so the
        // subcategory chip rail is reachable without a redundant category step.
        $defaultShopCategory = count($availableCategories) === 1
            ? $availableCategories[0]
            : '';

        // Subcategory IDs that appear on at least one product this day — only
        // these are shown as filter chips so every option narrows the grid.
        $subcategoryIdsOnDay = $products
            ->map(function (array $row): ?int {
                $id = $row['product']->subcategory_id;

                return $id !== null && (int) $id > 0 ? (int) $id : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $locale = app()->getLocale();
        $alpineSubcategories = ProductSubcategory::query()
            ->whereIn('id', $subcategoryIdsOnDay)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($g) => $g->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label($locale),
            ])->values()->all())
            ->all();

        return view('shop.index', [
            'availableDates' => $availableDates,
            'filterProducts' => $filterProducts,
            'activeProductIds' => $activeProductIds,
            'selectedDate' => $selectedDate,
            'products' => $products,
            'availableCategories' => $availableCategories,
            'defaultShopCategory' => $defaultShopCategory,
            'alpineSubcategories' => $alpineSubcategories,
            'pickupLocations' => PickupLocations::all(),
            'deliveryFeeEur' => $this->service->deliveryFee(),
            'quantityStep' => (int) config('shop.quantity_step', 50),
            'maxQuantity' => (int) config('shop.max_quantity', 3000),
            'maxCount' => (int) config('shop.max_count', 12),
        ]);
    }

    public function store(ShopOrderRequest $request): RedirectResponse
    {
        // Guard against duplicate in-person orders from back-button replay.
        // If the same email+date already has a PendingPayment in-person order
        // created within the last 10 minutes, redirect to that order instead
        // of creating another one. Online orders are not deduped here — their
        // SumUp checkout URL is idempotent and the pay page handles retries.
        if ($request->input('payment_method') === 'in_person') {
            $existing = ShopOrder::query()
                ->where('email', (string) $request->input('email'))
                ->whereDate('expected_delivery_date', (string) $request->input('expected_delivery_date'))
                ->where('payment_method', 'in_person')
                ->where('status', ShopOrderStatus::PendingPayment)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest()
                ->first();

            if ($existing !== null) {
                return redirect()->route('public.shop.return', ['shopOrder' => $existing->id]);
            }
        }

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

        // Ask customers (once, ever) whether they'd like to hear when the
        // subscription boxes launch. Shown on any completed order — online
        // (Paid) or pay-in-person (which stays PendingPayment until handed
        // over), mirroring the confirmation's happy state. Skipped if they've
        // already answered (homepage or a previous checkout), keyed by email.
        $showBoxPrompt = ShopOrderService::isCompletedCheckout($shopOrder)
            && filled($shopOrder->email)
            && ! BoxWaitlistSignup::query()
                ->where('email', mb_strtolower(trim($shopOrder->email)))
                ->exists();

        return view('shop.return', [
            'order' => $shopOrder->loadMissing([
                'items.product.translations',
                'items.product.producer.translations',
            ]),
            'showBoxPrompt' => $showBoxPrompt,
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
     * PDF receipt (Kleinbetragsrechnung) — always rendered in German.
     *
     * Guarded by a signed URL so order IDs cannot be enumerated by guests.
     * No expiry: the customer can re-download at any time via the link in
     * their confirmation email or from the return/track page.
     */
    public function receipt(Request $request, ShopOrder $shopOrder): SymfonyResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $order = $shopOrder->loadMissing([
            'items.product.translations',
            'items.product.producer',
        ]);

        $pdf = Pdf::loadView('shop.pdf.receipt', [
            'order' => $order,
            'vatByRate' => ShopOrderService::vatByRate($order),
            'company' => [
                'name' => (string) config('shop.company_name'),
                'street' => (string) config('shop.company_street'),
                'postal_city' => (string) config('shop.company_postal_city'),
                'phone' => (string) config('shop.company_phone'),
                'email' => (string) config('shop.company_email'),
                'vat_id' => (string) config('shop.company_vat_id'),
            ],
        ])->setPaper('a4');

        return $pdf->download('erdgut-beleg-'.$order->id.'.pdf');
    }

    /**
     * Record anonymous engagement beacons for the ranking bandit.
     *
     * The storefront batches "impression" (card scrolled into view) and
     * "add_to_cart" (first time a product's quantity goes 0 → >0) events and
     * flushes them here via navigator.sendBeacon. Aggregated into per-product
     * daily counters — no user data, no per-visitor rows. Cheap, throttled,
     * fire-and-forget (always 204); unknown product IDs are silently dropped.
     */
    public function trackEngagement(Request $request): SymfonyResponse
    {
        // No-op if the ranking feature is off or there's no real shop session.
        if (! (bool) config('shop.ranking.enabled', true) || ! $request->hasSession()) {
            return response()->noContent();
        }

        $data = $request->validate([
            'events' => ['required', 'array', 'max:200'],
            'events.*.product_id' => ['required', 'integer'],
            'events.*.type' => ['required', 'in:impression,add_to_cart'],
        ]);

        // Collapse to one increment per (product, type) so a payload can't be
        // used to inflate a single product's counters in one shot.
        $counts = []; // [productId][column] => n
        foreach ($data['events'] as $event) {
            $column = $event['type'] === 'add_to_cart' ? 'add_to_carts' : 'impressions';
            $pid = (int) $event['product_id'];
            $counts[$pid][$column] = ($counts[$pid][$column] ?? 0) + 1;
        }

        $validIds = Product::query()
            ->visibleInCatalog()
            ->whereIn('id', array_keys($counts))
            ->pluck('id')
            ->all();

        $today = Carbon::today()->toDateString();
        foreach ($validIds as $pid) {
            foreach ($counts[$pid] as $column => $by) {
                ProductEngagementDaily::bumpCounter((int) $pid, $column, $today, (int) $by);
            }
        }

        return response()->noContent();
    }

    /**
     * Per-session, per-day RNG seed for the storefront ranking. Stable within
     * a visit (no chaotic reshuffle on reload) yet rotates across sessions and
     * days so exposure keeps rotating.
     */
    private function storefrontSeed(Request $request): int
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : 'no-session';

        return (int) crc32($sessionId.'|'.Carbon::today()->toDateString());
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
