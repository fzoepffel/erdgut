<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CustomerType;
use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShopOrderRequest;
use App\Mail\ShopOrderConfirmationMail;
use App\Mail\ShopOutageReportedMail;
use App\Models\BoxWaitlistSignup;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductEngagementDaily;
use App\Models\ProductSubcategory;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Admin\ProducerAllocationService;
use App\Services\Shop\CheckoutDraft;
use App\Services\Shop\CouponService;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\ProductRankingService;
use App\Services\Shop\ShopOrderService;
use App\Support\Money;
use App\Support\PickupLocations;
use App\Support\ShopOrderAdminNotifier;
use App\Support\SumUpCheckoutLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        private readonly CheckoutDraft $draft,
    ) {}

    public function index(Request $request): View
    {
        // Abandoned-online-checkout recovery: the customer reached the SumUp
        // widget, didn't pay, and came back to /shop to edit. The draft still
        // remembers the placed order id (kept around so the basket can be
        // restored); if that order is still PendingPayment, the customer is
        // clearly starting over, so we cancel it to release its stock
        // immediately. Without this, they'd see their own basket as
        // "ausverkauft" for the next 30 minutes of the live-filter window.
        $placedOrderId = $this->draft->placedOrderId();
        if ($placedOrderId !== null) {
            $stale = ShopOrder::query()->find($placedOrderId);
            if ($stale !== null && $stale->status === ShopOrderStatus::PendingPayment) {
                $stale->status = ShopOrderStatus::Cancelled;
                $stale->save();
            }
            $this->draft->forgetPlacedOrderId();
        }

        $index = $this->service->availabilityIndex();
        $availableDates = $index['dates'];
        $filterProducts = $index['products'];

        // Resolve the active date. Priority:
        //   1. `?date=` query param if it's still on the menu (deep-link),
        //   2. the date held in the current checkout draft (so "edit basket"
        //      from steps 2–3 reopens on the same day),
        //   3. the first available date.
        // Null when the shop has no stock at all (empty-state view).
        $selectedDate = null;
        $requested = (string) $request->query('date', '');
        $menu = $availableDates->map(fn (array $row): string => $row['date']->toDateString())->all();
        $draftState = $this->draft->current();
        $draftDate = isset($draftState['expected_delivery_date']) ? (string) $draftState['expected_delivery_date'] : '';

        if ($requested !== '' && in_array($requested, $menu, true)) {
            $selectedDate = Carbon::parse($requested)->startOfDay();
        } elseif ($draftDate !== '' && in_array($draftDate, $menu, true)) {
            $selectedDate = Carbon::parse($draftDate)->startOfDay();
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

        // Draft items rehydrate the basket on return visits: the per-card
        // hidden quantity inputs read from this lookup (keyed by Alpine jsId
        // — "<productId>" for default grade, "v<variantId>" for named) when
        // neither old('quantities.<id>') nor old('items') seeded a value.
        $draftItems = $selectedDate !== null && ($draftState['expected_delivery_date'] ?? null) === $selectedDate->toDateString()
            ? (array) ($draftState['items'] ?? [])
            : [];

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
            'draftItems' => $draftItems,
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
    /**
     * Ground-truth pricing for the storefront. The frontend NEVER computes
     * prices itself: on every cart / mode / coupon change it posts the cart
     * here and renders the numbers this returns — the exact same
     * {@see ShopOrderService::quote} that persists the order at checkout, so
     * the displayed line totals, summary, VAT and grand total can never drift
     * from what the customer is actually charged.
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'fulfillment_mode' => ['required', 'in:pickup,delivery'],
            'expected_delivery_date' => ['nullable', 'date'],
        ]);

        $items = ShopOrderService::normalizeCartItems(
            (array) $request->input('quantities', []),
            (array) $request->input('variant_quantities', []),
        );

        $date = filled($data['expected_delivery_date'] ?? null)
            ? Carbon::parse((string) $data['expected_delivery_date'])->startOfDay()
            : null;

        $quote = $this->service->quote(
            $items,
            (string) $data['fulfillment_mode'],
            $data['coupon_code'] ?? null,
            $data['email'] ?? null,
            $date,
            CustomerType::Private,
        );

        // Per-line breakdown keyed exactly like the storefront's cart entries:
        // the default grade by product id, named grades as "v{variantId}".
        $lines = [];
        foreach ($quote['lines'] as $line) {
            /** @var Product $product */
            $product = $line['product'];
            $variantId = $line['variant_id'] ?? null;
            $key = $variantId !== null ? 'v'.$variantId : (string) $product->id;
            $name = $product->name.($line['variant_name_snapshot'] ? ' · '.$line['variant_name_snapshot'] : '');

            $lines[$key] = [
                'name' => $name,
                'qty_label' => $product->displayQuantity((float) $line['quantity']),
                'unit_price_label' => $this->formatUnitPrice($product, (float) $line['unit_price_eur']),
                'line_total' => (float) $line['line_total_eur'],
                'line_total_label' => Money::formatEur($line['line_total_eur']),
            ];
        }

        return response()->json([
            'lines' => $lines,
            'items_total' => $quote['items_total_eur'],
            'items_total_label' => Money::formatEur($quote['items_total_eur']),
            'delivery_fee' => $quote['delivery_fee_eur'],
            'delivery_fee_label' => Money::formatEur($quote['delivery_fee_eur']),
            'has_delivery_fee' => $quote['delivery_fee_eur'] > 0,
            'discount' => $quote['discount_eur'],
            'discount_label' => Money::formatEur($quote['discount_eur']),
            'has_discount' => $quote['discount_eur'] > 0,
            'total' => $quote['total_amount_eur'],
            'total_label' => Money::formatEur($quote['total_amount_eur']),
            'coupon' => [
                'applied' => $quote['coupon'] !== null,
                'code' => $quote['coupon_code'],
                'type' => $quote['coupon']?->type->value,
                'message' => $quote['coupon'] === null && $quote['coupon_error'] !== null
                    ? __('coupon.errors.'.$quote['coupon_error'], $quote['coupon_error_params'])
                    : null,
            ],
            'vat_lines' => $this->vatLinesForQuote($quote),
        ]);
    }

    /** Format a canonical (€/g) unit price in the product's display unit. */
    private function formatUnitPrice(Product $product, float $canonical): string
    {
        $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit();

        return $product->unit !== null && $displayUnit !== null
            ? $product->unit->formatPrice($canonical, $displayUnit, null, $product->countUnitWord())
            : Money::formatEur($canonical);
    }

    /**
     * Pre-formatted per-rate VAT summary lines for a quote (gross-inclusive),
     * grouping line VAT by rate and adding the delivery-fee VAT.
     *
     * @param  array<string, mixed>  $quote
     * @return list<string>
     */
    private function vatLinesForQuote(array $quote): array
    {
        $byRate = [];
        foreach ($quote['lines'] as $line) {
            $rate = (int) $line['vat_rate_percent'];
            $byRate[$rate] = ($byRate[$rate] ?? 0.0) + (float) $line['vat_amount_eur'];
        }
        if ((float) $quote['delivery_fee_vat_amount_eur'] > 0) {
            $rate = (int) $quote['delivery_fee_vat_rate_percent'];
            $byRate[$rate] = ($byRate[$rate] ?? 0.0) + (float) $quote['delivery_fee_vat_amount_eur'];
        }
        ksort($byRate);

        $lines = [];
        foreach ($byRate as $rate => $amount) {
            $lines[] = __('shop.summary_vat_rate_line', [
                'rate' => $rate,
                'amount' => Money::formatEur(round($amount, 2)),
            ]);
        }

        return $lines;
    }

    /**
     * Back-compat coupon preview (returns the legacy aggregate shape). The
     * storefront now uses {@see quote}; this stays for the coupon test suite.
     */
    public function couponPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'fulfillment_mode' => ['required', 'in:pickup,delivery'],
        ]);

        $items = ShopOrderService::normalizeCartItems(
            (array) $request->input('quantities', []),
            (array) $request->input('variant_quantities', []),
        );

        $previewDate = filled($request->input('expected_delivery_date'))
            ? Carbon::parse((string) $request->input('expected_delivery_date'))->startOfDay()
            : null;

        $quote = $this->service->quote(
            $items,
            (string) $data['fulfillment_mode'],
            $data['coupon_code'] ?? null,
            $data['email'] ?? null,
            $previewDate,
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

        // The checkout draft was kept alive across the SumUp redirect so a
        // failed/abandoned payment would restore the basket. The order is
        // now settled (paid, failed, or pay-in-person), so the draft can go.
        if ($this->draft->placedOrderId() === $shopOrder->id) {
            $this->draft->clear();
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

        [$producerNames, $producerModels] = $this->orderProducerDisplay($shopOrder);

        return view('shop.return', [
            'order' => $shopOrder->loadMissing([
                'items.product.translations',
                'items.product.producer.translations',
            ]),
            'showBoxPrompt' => $showBoxPrompt,
            'producerNames' => $producerNames,
            'producerModels' => $producerModels,
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

        [$producerNames, $producerModels] = $this->orderProducerDisplay($shopOrder);

        return view('shop.track', [
            'order' => $shopOrder->loadMissing([
                'items.product.translations',
                'items.product.producer.translations',
            ]),
            'producerNames' => $producerNames,
            'producerModels' => $producerModels,
        ]);
    }

    /**
     * Allocation-based farm display for one order: per-line labels keyed
     * "productId|variantKey" (the farms whose recorded inflows cover the
     * order's delivery date, like the admin packing labels) plus the
     * Producer models behind them for the "meet the farms" section. Views
     * fall back to the product's primary producer for missing keys.
     *
     * @return array{0: array<string, string>, 1: Collection<int, Producer>}
     */
    private function orderProducerDisplay(ShopOrder $order): array
    {
        $date = $order->expected_delivery_date;
        $productIds = $order->items->pluck('product_id')->filter()->map(fn ($v): int => (int) $v)->unique()->values()->all();
        if ($date === null || $productIds === []) {
            return [[], collect()];
        }

        $allocation = app(ProducerAllocationService::class);
        $names = $allocation->namesByItemKey($productIds, Carbon::parse($date));

        $producerIds = [];
        foreach ($allocation->summaryForDate($productIds, Carbon::parse($date)) as $byVkey) {
            foreach ($byVkey as $rows) {
                foreach ($rows as $row) {
                    if ($row['producerId'] > 0) {
                        $producerIds[$row['producerId']] = true;
                    }
                }
            }
        }
        $models = $producerIds === []
            ? collect()
            : Producer::query()->with('translations')->whereIn('id', array_keys($producerIds))->get();

        return [$names, $models];
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

        // Render the receipt in the language the order was placed in — not the
        // locale of whoever is downloading it (e.g. an admin on an English
        // session), so product names + units stay in the customer's language.
        if ($order->locale) {
            app()->setLocale($order->locale);
        }

        [$producerNames] = $this->orderProducerDisplay($order);

        $pdf = Pdf::loadView('shop.pdf.receipt', [
            'order' => $order,
            'vatByRate' => ShopOrderService::vatByRate($order),
            'producerNames' => $producerNames,
            'company' => [
                'name' => (string) config('shop.company_name'),
                'street' => (string) config('shop.company_street'),
                'postal_city' => (string) config('shop.company_postal_city'),
                'phone' => (string) config('shop.company_phone'),
                'email' => (string) config('shop.company_email'),
                'vat_id' => (string) config('shop.company_vat_id'),
                'register' => (string) config('shop.company_register'),
            ],
        ])->setPaper('a4');

        return $pdf->download('erdgut-beleg-'.$order->id.'.pdf');
    }

    /**
     * A visitor pressed the notify button on the "nothing in stock"
     * screen. The shop is expected to always carry stock, so this is
     * treated as an incident signal: mail every admin, but at most once
     * per hour no matter how many people press it.
     */
    public function reportOutage(): RedirectResponse
    {
        $alreadyReported = ! Cache::add('shop.outage.reported', now()->toDateTimeString(), now()->addHour());

        if (! $alreadyReported) {
            $reportedAt = now()->locale('de')->isoFormat('dd, D. MMM YYYY, HH:mm');
            foreach (User::query()->where('is_admin', true)->pluck('email') as $email) {
                try {
                    Mail::to($email)->queue(new ShopOutageReportedMail($reportedAt));
                } catch (Throwable $e) {
                    Log::warning('shop.outage_report.send_failed', [
                        'recipient' => $email,
                        'exception_class' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()
            ->route('public.shop.index')
            ->with('outage_reported', true);
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
            Mail::to($order->email)
                ->locale($order->locale ?? app()->getLocale())
                ->queue(new ShopOrderConfirmationMail($order));
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
