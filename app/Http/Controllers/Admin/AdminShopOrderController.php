<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantVerificationStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Exports\ShopOrdersExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SpargelAdminController;
use App\Http\Requests\Admin\AdminCreateShopOrderRequest;
use App\Http\Requests\Admin\AdminUpdateShopOrderRequest;
use App\Mail\ShopOrderWindowMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Admin\ProducerAllocationService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
use App\Services\Shop\CouponService;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopOrderService;
use App\Support\AuditLogger;
use App\Support\PickupLocations;
use App\Support\RestaurantAccountProvisioner;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

/**
 * Proper admin surface for anonymous {@see ShopOrder}s.
 *
 * Unlike the disposable {@see SpargelAdminController}
 * (standalone page, no shell, no audit log), this lives inside the main
 * `app.admin.*` group: shares the sidebar shell, status filtering, and
 * writes every state change to the audit log. Mirrors the spargel admin's
 * action set generalized to multi-product orders.
 *
 *   GET  /app/admin/shop-orders                       list + filter
 *   GET  /app/admin/shop-orders/{shopOrder}           detail
 *   POST /app/admin/shop-orders/{shopOrder}/mark-paid       in-person orders
 *   POST /app/admin/shop-orders/{shopOrder}/mark-completed  paid + fulfilled
 *   POST /app/admin/shop-orders/{shopOrder}/refund          refund a paid order
 */
class AdminShopOrderController extends Controller
{
    public function __construct(
        private readonly ShopOrderService $service,
        private readonly PriceResolver $priceResolver,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * JSON list of orderable grades (product × optional variant) for a
     * specific date. Each entry carries the full pricing breakdown
     * needed by the admin order form: both customer-type base prices,
     * the tiers that apply to each customer type, the VAT rate, and
     * the canonical-unit prices used for line-total auto-fill.
     */
    public function availableProductsForDate(Request $request): JsonResponse
    {
        $dateStr = (string) $request->query('date', '');
        if ($dateStr === '') {
            return response()->json([]);
        }

        $date = Carbon::parse($dateStr)->startOfDay();
        $customerType = $request->query('customer_type') === CustomerType::Business->value
            ? CustomerType::Business
            : CustomerType::Private;
        $audience = $customerType === CustomerType::Business
            ? ShopStockAudience::BusinessTrade
            : ShopStockAudience::Consumer;

        $grades = [];
        // Whole catalog, including out-of-stock grades: the admin may
        // deliberately sell beyond stock (goods procured on demand), so
        // nothing is hidden — remaining 0 is flagged in the form instead.
        foreach ($this->service->availableProductsForDate($date, $audience, includeOutOfStock: true) as $row) {
            /** @var Product $product */
            $product = $row['product'];
            // priceRules + variants are needed for grade enumeration and tier
            // resolution. variant_translations doesn't exist as a relation —
            // variant names live on a JSON column read by localizedName().
            $product->loadMissing('priceRules', 'variants');
            $variants = $product->activeVariants();

            // Default grade: present when the product has no named variants,
            // or when the default itself has stock that day.
            $defaultRemaining = $variants->isEmpty()
                ? (float) $row['remaining']
                : (float) $this->inventory->remainingForGrade($product, $date, null);
            if ($variants->isEmpty() || $defaultRemaining > 0) {
                $grades[] = $this->buildGrade($product, null, $defaultRemaining, $date, $customerType);
            }

            // Named grades — out-of-stock ones included, flagged via remaining.
            foreach ($variants as $variant) {
                $remaining = (float) $this->inventory->remainingForGrade($product, $date, $variant->id);
                $grades[] = $this->buildGrade($product, $variant, $remaining, $date, $customerType);
            }
        }

        $grades = $this->addBackOwnQuantities($grades, $request, $date, $customerType);

        return response()->json($grades);
    }

    /**
     * Order-edit support: the availability matrix counts the edited order's
     * own quantities as committed, so without compensation the edit form
     * would show its own order as "out of stock". When `exclude_order_id`
     * is present (and the requested date matches the order's), each of the
     * order's grades gets its own committed quantity added back onto
     * `remaining`; grades that vanished from the list entirely (stock fully
     * consumed by this order, or product unpublished since purchase) are
     * re-appended so the prefilled lines stay selectable.
     *
     * @param  array<int, array<string, mixed>>  $grades
     * @return array<int, array<string, mixed>>
     */
    private function addBackOwnQuantities(array $grades, Request $request, Carbon $date, CustomerType $customerType): array
    {
        $excludeOrderId = (int) $request->query('exclude_order_id', 0);
        if ($excludeOrderId <= 0) {
            return $grades;
        }

        $order = ShopOrder::query()->with('items')->find($excludeOrderId);
        if ($order === null || ! $order->commitsStockNow() || ! $date->isSameDay($order->expected_delivery_date)) {
            return $grades;
        }

        $own = [];
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }
            $key = $item->variant_id !== null
                ? $item->product_id.':'.$item->variant_id
                : (string) $item->product_id;
            $own[$key] = ($own[$key] ?? 0.0) + (float) $item->quantity;
        }

        $indexByKey = [];
        foreach ($grades as $i => $grade) {
            $indexByKey[(string) $grade['key']] = $i;
        }

        foreach ($own as $key => $quantity) {
            if (isset($indexByKey[$key])) {
                $grades[$indexByKey[$key]]['remaining'] = (float) $grades[$indexByKey[$key]]['remaining'] + $quantity;

                continue;
            }

            [$productId, $variantId] = str_contains((string) $key, ':')
                ? array_map('intval', explode(':', (string) $key, 2))
                : [(int) $key, null];

            $product = Product::query()->with(['priceRules', 'variants'])->find($productId);
            if ($product === null) {
                continue;
            }
            $variant = $variantId !== null ? $product->variants->firstWhere('id', $variantId) : null;

            $remaining = (float) $this->inventory->remainingForGrade($product, $date, $variantId) + $quantity;
            $grades[] = $this->buildGrade($product, $variant, $remaining, $date, $customerType);
        }

        return $grades;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGrade(
        Product $product,
        ?ProductVariant $variant,
        float $remaining,
        Carbon $date,
        CustomerType $customerType,
    ): array {
        $altCustomerType = $customerType === CustomerType::Business
            ? CustomerType::Private
            : CustomerType::Business;

        $privateResolved = $this->priceResolver->resolve(
            $product, $date, CustomerType::Private, null, $variant?->id, $variant,
        );
        $businessResolved = $this->priceResolver->resolve(
            $product, $date, CustomerType::Business, null, $variant?->id, $variant,
        );
        // The picked tier is whichever the current customer type maps to.
        $picked = $customerType === CustomerType::Business ? $businessResolved : $privateResolved;
        $alt = $altCustomerType === CustomerType::Business ? $businessResolved : $privateResolved;

        $gradeKey = $variant !== null ? $product->id.':'.$variant->id : (string) $product->id;
        $gradeLabel = $variant?->localizedName();
        $displayName = $variant !== null ? $product->name.' · '.$gradeLabel : $product->name;

        return [
            'key' => $gradeKey,
            'id' => $gradeKey, // dropdown <option value=...>
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'grade_label' => $gradeLabel,
            'name' => $displayName,
            'producer' => $product->producer?->name ?? '',
            'unit' => $product->canonicalUnitLabel(),
            'display_unit' => $product->localizedUnit(),
            'remaining' => $remaining,
            'vat_rate_percent' => (int) ($product->vat_rate_percent ?? 7),
            // Tier-aware display price for the CURRENTLY selected customer
            // type — drives the line-total autofill.
            'price' => $this->formatDisplayPrice($product, $picked->unitPriceEur),
            'unit_price_eur' => $picked->unitPriceEur,
            'price_reason' => $picked->reason,
            // Same fields for the OTHER customer type — for the comparison
            // line of the price breakdown.
            'alt_price' => $this->formatDisplayPrice($product, $alt->unitPriceEur),
            'alt_unit_price_eur' => $alt->unitPriceEur,
            'alt_price_reason' => $alt->reason,
            // Untyped volume tiers (the standard "ab 1 kg: 1,00 €/kg" rules)
            // apply to private customers only; business gets the flat
            // customer-group price. The breakdown surfaces those tiers next
            // to the private base so the admin can sanity-check at a glance.
            'private_tiers' => $this->tiersFor($product, $variant, formatted: true),
        ];
    }

    /**
     * Untyped (date-open, customer-type-null) volume tiers for the given
     * grade — these are private-customer discounts; business orders never
     * get them. Returned as a list of localized strings already formatted
     * (e.g. "ab 1 kg: 1,00 €/kg") so the admin form can render them
     * directly in the breakdown.
     *
     * @return list<array{min_qty: float, price_per_unit_eur: float, label: string}>
     */
    private function tiersFor(Product $product, ?ProductVariant $variant, bool $formatted = false): array
    {
        $variantId = $variant?->id;

        return $product->priceRules
            ->filter(function ($r) use ($variantId): bool {
                if (! $r->is_active) {
                    return false;
                }
                if ($r->customer_type !== null || $r->starts_on !== null || $r->ends_on !== null) {
                    return false;
                }
                if ($r->min_quantity === null) {
                    return false;
                }

                return ((int) $r->variant_id) === ((int) $variantId);
            })
            ->sortBy(fn ($r) => (float) $r->min_quantity)
            ->map(fn ($r) => [
                'min_qty' => (float) $r->min_quantity,
                'price_per_unit_eur' => (float) $r->unit_price,
                'label' => $formatted ? __('shop.tier_from', [
                    'qty' => $product->displayQuantity((float) $r->min_quantity),
                    'price' => $this->formatDisplayPrice($product, (float) $r->unit_price),
                ]) : '',
            ])
            ->values()
            ->all();
    }

    /**
     * Localized "9,00 €/kg" string for a per-canonical-unit price, using
     * the product's chosen display unit (kg/L/piece/bunch).
     */
    private function formatDisplayPrice(Product $product, float $unitPriceEur): string
    {
        if ($product->unit === null) {
            return number_format($unitPriceEur, 2, ',', '.').' €';
        }

        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();

        return $product->unit->formatPrice($unitPriceEur, $displayUnit, null, $product->countUnitWord());
    }

    /**
     * JSON quote for the admin create form — catalog-priced defaults for
     * product total, delivery fee, and grand total before manual overrides.
     */
    public function quoteForCreate(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['message' => 'items required'], 422);
        }

        $fulfillmentMode = (string) $request->input('fulfillment_mode', 'pickup');
        $couponCode = $request->input('coupon_code');
        $normalizedCoupon = is_string($couponCode) && trim($couponCode) !== '' ? trim($couponCode) : null;

        $quoteDate = filled($request->input('expected_delivery_date'))
            ? Carbon::parse((string) $request->input('expected_delivery_date'))->startOfDay()
            : null;
        $customerType = $request->input('customer_type') === CustomerType::Business->value
            ? CustomerType::Business
            : CustomerType::Private;

        // Admin endpoint: tolerate products unpublished after purchase so the
        // edit form's quote keeps covering every prefilled line (the picker
        // itself never offers hidden products for new lines).
        $quote = $this->service->quote(
            $items,
            $fulfillmentMode,
            $normalizedCoupon,
            null,
            $quoteDate,
            $customerType,
            includeHiddenProducts: true,
        );

        if ($quote['lines'] === []) {
            return response()->json(['message' => 'empty cart'], 422);
        }

        $vatByRate = [];
        foreach ($quote['lines'] as $line) {
            $rate = (int) $line['vat_rate_percent'];
            $vatByRate[$rate] = round(($vatByRate[$rate] ?? 0) + (float) $line['vat_amount_eur'], 2);
        }

        return response()->json([
            'items_total_eur' => $quote['items_total_eur'],
            'delivery_fee_eur' => $quote['delivery_fee_eur'],
            'delivery_fee_vat_rate_percent' => $quote['delivery_fee_vat_rate_percent'],
            'discount_eur' => $quote['discount_eur'],
            'total_amount_eur' => $quote['total_amount_eur'],
            'vat_by_rate' => $vatByRate,
            'lines' => collect($quote['lines'])
                ->map(fn (array $line): array => [
                    'product_id' => $line['product']->id,
                    'variant_id' => $line['variant_id'] ?? null,
                    'unit_price_eur' => $line['unit_price_eur'] ?? null,
                    'line_total_eur' => $line['line_total_eur'],
                    'vat_rate_percent' => $line['vat_rate_percent'],
                    'vat_amount_eur' => $line['vat_amount_eur'],
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Show the form for manually creating a shop order.
     */
    public function create(Request $request): View
    {
        // Verified restaurant accounts for the recurring-delivery option.
        $restaurantAccounts = RestaurantProfile::query()
            ->where('verification_status', RestaurantVerificationStatus::Verified->value)
            ->with('user')
            ->get()
            ->map(fn ($p) => [
                'user_id' => $p->user_id,
                'label' => trim(($p->business_name ?: ($p->user?->name ?? '')).' · '.($p->user?->email ?? '')),
                // Prefill payload for the create form.
                'name' => $p->contact_name ?: ($p->user?->name ?? ''),
                'email' => $p->user?->email ?? '',
                'phone' => $p->user?->phone ?? '',
                'street' => $p->billing_street ?? '',
                'postal' => $p->billing_postal_code ?? '',
                'city' => $p->billing_city ?? '',
            ])
            ->sortBy('label')
            ->values();

        return view('admin.shop-orders.create', [
            'pickupLocations' => PickupLocations::all(),
            'availableProductsUrl' => route('app.admin.shop-orders.available-products'),
            'quoteUrl' => route('app.admin.shop-orders.quote'),
            'restaurantAccounts' => $restaurantAccounts,
        ]);
    }

    /**
     * Persist a manually-entered shop order.
     *
     * Reuses {@see ShopOrderService::createOrder()} so stock checking, VAT
     * snapshots, and the transaction race-lock are identical to the storefront
     * flow. No automated emails are sent — the admin is in direct contact with
     * the buyer, and the observer guard ({@see ShopOrderObserver}) also prevents
     * any later notifications on status changes.
     */
    public function store(AdminCreateShopOrderRequest $request): RedirectResponse
    {
        // Recurring weekly B2B delivery → the restaurant order machinery (net
        // price list, standing order, horizon materialization) instead of a
        // one-off shop order. Validated in the form request.
        if ($request->boolean('recurring')) {
            return $this->storeRecurring($request);
        }

        try {
            $order = $this->service->createOrder(array_merge(
                $request->validated(),
                [
                    'order_source' => OrderSource::Admin->value,
                    'customer_type' => $request->input('customer_type', CustomerType::Private->value),
                    'payment_method' => 'in_person', // admin orders are always pay-at-pickup
                ]
            ));
        } catch (InsufficientStockException $e) {
            return back()
                ->withInput()
                ->with('error', __('shop.errors.stock_vanished', ['name' => $e->product->name]));
        }

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $order->id,
            action: 'create',
            fromState: null,
            toState: ShopOrderStatus::PendingPayment->value,
            reason: null,
            metadata: ['channel' => 'admin', 'customer_type' => $order->customer_type->value],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.shop-orders.show', $order)
            ->with('status', __('shop.admin.order_created_success'));
    }

    /**
     * Create a weekly recurring B2B delivery on behalf of a verified
     * restaurant account. Prices come from the B2B price list (the form's
     * negotiated line totals don't apply here); every line repeats weekly
     * until the chosen end. The standing order activates immediately, so all
     * upcoming weeks materialize as real orders.
     */
    private function storeRecurring(AdminCreateShopOrderRequest $request): RedirectResponse
    {
        $accountId = (int) ($request->validated('restaurant_user_id') ?? 0);
        $user = $accountId > 0
            ? User::findOrFail($accountId)
            : app(RestaurantAccountProvisioner::class)->findOrCreateVerified(
                (string) $request->validated('name'),
                (string) $request->validated('email'),
                [
                    'street' => (string) $request->validated('delivery_street'),
                    'postal_code' => (string) $request->validated('delivery_postal_code'),
                    'city' => (string) $request->validated('delivery_city'),
                ],
                (int) $request->user()->id,
            );
        $until = (string) ($request->validated('recurring_until') ?? '');

        $items = collect($request->validated('items'))->map(fn (array $line): array => [
            'product_id' => (int) $line['product_id'],
            'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            'quantity' => (float) $line['quantity'],
            'recurrence' => 'weekly',
            'until' => $until !== '' ? $until : null,
        ])->values()->all();

        try {
            $order = app(RestaurantOrderService::class)->submitOrder($user, [
                'items' => $items,
                'expected_delivery_date' => $request->validated('expected_delivery_date'),
                'delivery_street' => $request->validated('delivery_street'),
                'delivery_postal_code' => $request->validated('delivery_postal_code'),
                'delivery_city' => $request->validated('delivery_city'),
                'delivery_notes' => $request->validated('delivery_notes'),
                'locale' => $user->locale ?? app()->getLocale(),
            ]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $standingService = app(RestaurantStandingOrderService::class);
        $standingOrder = $standingService->createFromOrder($order, $items);
        if ($standingOrder !== null && $standingOrder->isPending()) {
            $standingService->activate($standingOrder, (int) $request->user()->id);
        }

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $order->id,
            action: 'create_recurring',
            fromState: null,
            toState: $order->status->value,
            metadata: ['channel' => 'admin', 'standing_order_id' => $standingOrder?->id, 'until' => $until !== '' ? $until : null],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.restaurant-orders.show', $order)
            ->with('status', __('shop.admin.recurring_created_success'));
    }

    /**
     * Default filter: only "finalized" orders — confirmed sales the team
     * actually has to act on. That means paid orders, already-fulfilled
     * orders, and the in-person ones still awaiting payment at pickup
     * ("Zahlung bei Abholung"). Abandoned online checkouts, failed,
     * cancelled and refunded orders are hidden unless explicitly chosen.
     */
    private const FILTER_FINALIZED = 'finalized';

    /**
     * ONE order overview: every ShopOrder, with a type filter. B2B restaurant
     * orders enter this list once granted through their mirror ShopOrder
     * (created by RestaurantOrderService::ensureShopOrderMirror); unconfirmed
     * requests still live in the Anfragen queue (linked in the header with an
     * open count) until granted.
     */
    public function index(Request $request): View
    {
        $filter = $this->resolveFilter($request);
        $perPage = 30;
        $page = max(1, (int) $request->query('page', 1));

        $shopQuery = $this->filteredOrdersQuery($filter);

        $orders = $shopQuery
            ->with(['items.product.translations', 'sourceRestaurantOrder'])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $openRequests = RestaurantOrder::query()
            ->requests()
            ->where('status', RestaurantOrderStatus::Submitted->value)
            ->count();

        // Calendar starts on the month of the current selection (or today).
        $initialMonth = $filter['delivery_from'] !== ''
            ? Carbon::parse($filter['delivery_from'])->startOfMonth()
            : Carbon::today()->startOfMonth();

        return view('admin.shop-orders.index', [
            'orders' => $orders,
            'statuses' => ShopOrderStatus::cases(),
            'status' => $filter['status'],
            'type' => $filter['type'],
            'deliveryFrom' => $filter['delivery_from'],
            'deliveryTo' => $filter['delivery_to'],
            'openRequests' => $openRequests,
            'calendarInitialMonth' => $initialMonth->format('Y-m'),
            'calendarDays' => $this->calendarDays($initialMonth->copy()->startOfMonth(), $initialMonth->copy()->endOfMonth()),
        ]);
    }

    /** Next date (today or later) that has any finalized/firm order. */
    private function nextDeliveryDayWithOrders(): ?string
    {
        $shopNext = ShopOrder::query()
            ->finalized()
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->orderBy('expected_delivery_date')
            ->value('expected_delivery_date');
        $restaurantNext = RestaurantOrder::query()
            ->whereIn('status', [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value])
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->orderBy('expected_delivery_date')
            ->value('expected_delivery_date');

        $candidates = array_filter([$shopNext, $restaurantNext]);

        return $candidates === [] ? null : Carbon::parse(min($candidates))->toDateString();
    }

    /**
     * JSON month payload for the calendar filter (any month, past or future):
     * per-day order count (B2C finalized + B2B firm) and in-stock product
     * count. Past days skip the stock computation — only deliveries matter.
     */
    public function calendarMonth(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $month = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return response()->json(['days' => []], 422);
        }
        $start = Carbon::parse($month.'-01')->startOfMonth();

        return response()->json(['days' => $this->calendarDays($start, $start->copy()->endOfMonth())]);
    }

    /**
     * Per-day metadata for the calendar filter: order count (B2C finalized +
     * B2B firm) and in-stock product count. Green = has deliveries, yellow =
     * stock but no deliveries.
     *
     * @return list<array{iso: string, orders: int, stock: int}>
     */
    private function calendarDays(Carbon $start, Carbon $end): array
    {

        $shopCounts = ShopOrder::query()
            ->finalized()
            ->whereBetween('expected_delivery_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('DATE(expected_delivery_date) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');
        // Mirrored restaurant orders are already counted via ShopOrder above;
        // skip them here so a single logical order counts once.
        $restaurantCounts = RestaurantOrder::query()
            ->whereIn('status', [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value])
            ->whereBetween('expected_delivery_date', [$start->toDateString(), $end->toDateString()])
            ->whereDoesntHave('mirroredShopOrder')
            ->selectRaw('DATE(expected_delivery_date) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        // Widest stock view (shared + B2B pools) so "yellow" means anyone could
        // buy. Stock is only meaningful from today onward — past days skip it.
        $stockStart = $start->lt(Carbon::today()) ? Carbon::today() : $start->copy();
        $matrix = [];
        $products = collect();
        if ($stockStart->lte($end)) {
            $products = Product::query()->visibleInCatalog()->with('variants')->get();
            $matrix = $this->inventory->availabilityMatrix($products, $stockStart, $end, ShopStockAudience::BusinessTrade);
        }

        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $iso = $cursor->toDateString();
            $stock = 0;
            if ($cursor->gte($stockStart)) {
                foreach ($products as $product) {
                    if ((float) ($matrix[$product->id][$iso] ?? 0.0) > 0.0) {
                        $stock++;
                    }
                }
            }
            $days[] = [
                'iso' => $iso,
                'orders' => (int) ($shopCounts[$iso] ?? 0) + (int) ($restaurantCounts[$iso] ?? 0),
                'stock' => $stock,
            ];
        }

        return $days;
    }

    /**
     * Restaurant orders that belong in the unified overview: firm artifacts
     * only (confirmed / fulfilled / cancelled) — pending requests stay in the
     * Anfragen queue. Returns null when the current filter can't match any
     * restaurant order (e.g. B2C-only or a payment-specific status).
     */
    private function filteredRestaurantOrdersQuery(array $filter): ?Builder
    {
        if ($filter['type'] === 'b2c') {
            return null;
        }

        // Map the shop-status filter onto restaurant statuses.
        $statusMap = [
            self::FILTER_FINALIZED => [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value],
            'all' => [
                RestaurantOrderStatus::Confirmed->value,
                RestaurantOrderStatus::Fulfilled->value,
                RestaurantOrderStatus::Cancelled->value,
            ],
            ShopOrderStatus::Paid->value => [RestaurantOrderStatus::Confirmed->value],
            ShopOrderStatus::Fulfilled->value => [RestaurantOrderStatus::Fulfilled->value],
            ShopOrderStatus::Cancelled->value => [RestaurantOrderStatus::Cancelled->value],
        ];
        $statuses = $statusMap[$filter['status']] ?? null;
        if ($statuses === null) {
            return null; // payment-specific status — no B2B equivalent
        }

        return RestaurantOrder::query()
            ->whereIn('status', $statuses)
            ->when($filter['delivery_from'] !== '', fn ($q) => $q->whereDate('expected_delivery_date', '>=', $filter['delivery_from']))
            ->when($filter['delivery_to'] !== '', fn ($q) => $q->whereDate('expected_delivery_date', '<=', $filter['delivery_to']));
    }

    /**
     * Stream the filtered orders set as a two-sheet Excel workbook
     * (per-customer deliveries + per-farm supply totals). Reuses the
     * exact same filter as {@see index()} so admins export "what they see".
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filter = $this->resolveFilter($request);

        $orders = $this->filteredOrdersQuery($filter)
            ->with('items.product.producer.translations', 'items.product.translations')
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->get();

        // B2B demand rides along so farm totals, picklist and route reflect
        // the full day, not just the storefront.
        $restaurantOrders = $this->filteredRestaurantOrdersQuery($filter)
            ?->with(['user.restaurantProfile', 'items.product.producer.translations', 'items.product.translations'])
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->get() ?? collect();

        $filename = 'erdgut-bestellungen-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ShopOrdersExport($orders, $filter, $restaurantOrders), $filename);
    }

    /**
     * JSON list of producers that have at least one ordered item in the
     * currently-filtered orders. Drives the "pick a farm" select in the
     * Hofbestellung export flow on the orders list page.
     */
    public function farmerExportProducers(Request $request): JsonResponse
    {
        $filter = $this->resolveFilter($request);

        // Attribution-based: a farm appears when the allocation assigns it a
        // share of the filtered demand (batches first, primary fallback for
        // uncovered demand — see ProducerAllocationService).
        $orders = $this->filteredOrdersQuery($filter)
            ->with(['items.product'])
            ->get();
        $allocation = $this->farmerAllocation($orders);
        $ids = [];
        foreach ($allocation as $byPid) {
            foreach ($byPid as $byVkey) {
                foreach ($byVkey as $shares) {
                    foreach ($shares as $producerId => $qty) {
                        if ($producerId > 0 && $qty > 1e-9) {
                            $ids[$producerId] = true;
                        }
                    }
                }
            }
        }
        $producers = array_keys($ids);

        $rows = Producer::query()
            ->whereIn('id', $producers)
            ->with('translations')
            ->get()
            ->map(fn (Producer $p): array => ['id' => $p->id, 'name' => $p->name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return response()->json($rows);
    }

    /**
     * Build a ZIP holding a PDF and a CSV summarising what Erdgut needs
     * from one specific farmer over the currently-filtered window. The
     * CSV is the editable fallback if the admin wants to tweak anything
     * before sending; the PDF is the "ready to send" copy.
     */
    public function farmerExport(Request $request): BinaryFileResponse
    {
        $filter = $this->resolveFilter($request);
        $producerId = (int) $request->query('producer_id', 0);
        abort_unless($producerId > 0, 422, 'producer_id is required');

        $producer = Producer::with('translations')->find($producerId);
        abort_unless($producer !== null, 404);

        $orders = $this->filteredOrdersQuery($filter)
            ->with([
                'items.product.producer.translations',
                'items.product.translations',
                'items.product.variants',
            ])
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->get();

        $rows = $this->buildFarmerOrderRows($orders, $producerId);

        $slug = Str::slug($producer->name) ?: 'hof';
        $today = now()->format('Y-m-d');
        $base = "erdgut-hofbestellung-{$slug}-{$today}";

        $pdfContent = Pdf::loadView('admin.shop-orders.farmer-order-pdf', [
            'producer' => $producer,
            'filter' => $filter,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->output();

        $csvContent = $this->buildFarmerOrderCsv($rows);

        $tempZip = tempnam(sys_get_temp_dir(), 'farmer-export-');
        $zip = new ZipArchive;
        $zip->open($tempZip, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        $zip->addFromString($base.'.pdf', $pdfContent);
        // UTF-8 BOM so Excel opens the CSV with the right encoding by default.
        $zip->addFromString($base.'.csv', "\xEF\xBB\xBF".$csvContent);
        $zip->close();

        return Response::download($tempZip, $base.'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    /**
     * Aggregate the filtered orders' items into one row per (date, product,
     * variant) for a single producer, with the canonical quantity converted
     * to the product's display unit.
     *
     * @param  Collection<int, ShopOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function buildFarmerOrderRows($orders, int $producerId): array
    {
        // Demand + display metadata from the filtered order set, then the
        // allocation decides which farm covers which share (batches first,
        // uncovered demand falls back to the product's primary producer).
        $allocation = $this->farmerAllocation($orders);

        $meta = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product === null) {
                    continue;
                }
                $metaKey = $product->id.'|'.($item->variant_id ?? 0);
                if (isset($meta[$metaKey])) {
                    continue;
                }
                $variant = $item->variant_id !== null
                    ? $product->variants->firstWhere('id', $item->variant_id)
                    : null;
                $unit = $product->unit;
                $displayUnit = $product->price_display_unit ?? $unit?->defaultDisplayUnit();
                $meta[$metaKey] = [
                    'product_name' => $product->name,
                    'variant_name' => $variant?->localizedName(),
                    'factor' => ($unit !== null && $displayUnit !== null)
                        ? $unit->canonicalUnitsPerDisplay($displayUnit)
                        : 1,
                    'unit' => $product->localizedUnit(),
                ];
            }
        }

        $rows = [];
        foreach ($allocation as $iso => $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    $qty = (float) ($shares[$producerId] ?? 0.0);
                    if ($qty <= 1e-9) {
                        continue;
                    }
                    $m = $meta[$pid.'|'.($vkey ?: 0)] ?? null;
                    if ($m === null) {
                        continue;
                    }
                    $rows[] = [
                        'date' => Carbon::parse($iso),
                        'product_name' => $m['product_name'],
                        'variant_name' => $m['variant_name'],
                        'qty_display' => round($m['factor'] > 0 ? $qty / $m['factor'] : $qty, 4),
                        'unit' => $m['unit'],
                    ];
                }
            }
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['date']->toDateString(), $a['product_name'], (string) ($a['variant_name'] ?? '')]
                <=> [$b['date']->toDateString(), $b['product_name'], (string) ($b['variant_name'] ?? '')];
        });

        return $rows;
    }

    /**
     * Allocation of the given order set's demand across producers:
     * [iso][pid][vkey][producerId => qty], demand injected from exactly
     * these orders (the farmer export's filter set, not global commitments).
     */
    private function farmerAllocation($orders): array
    {
        $demand = [];
        $dates = [];
        foreach ($orders as $order) {
            $iso = $order->expected_delivery_date?->toDateString();
            if ($iso === null) {
                continue;
            }
            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue; // custom lines have no farm
                }
                $pid = (int) $item->product_id;
                $vkey = (int) ($item->variant_id ?? 0);
                $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + (float) $item->quantity;
                $dates[$iso] = true;
            }
        }
        if ($demand === []) {
            return [];
        }

        return app(ProducerAllocationService::class)
            ->allocate(array_keys($demand), array_keys($dates), $demand);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function buildFarmerOrderCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            __('shop.admin.farmer_export_col_date'),
            __('shop.admin.farmer_export_col_product'),
            __('shop.admin.farmer_export_col_variant'),
            __('shop.admin.farmer_export_col_quantity'),
            __('shop.admin.farmer_export_col_unit'),
        ]);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['date']->format('Y-m-d'),
                $row['product_name'],
                (string) ($row['variant_name'] ?? ''),
                number_format((float) $row['qty_display'], 4, '.', ''),
                (string) $row['unit'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @return array{status: string, delivery_from: string, delivery_to: string}
     */
    private function resolveFilter(Request $request): array
    {
        $statusParam = $request->query('status', self::FILTER_FINALIZED);
        $status = is_string($statusParam) ? $statusParam : self::FILTER_FINALIZED;
        $from = (string) $request->query('delivery_from', '');
        $to = (string) $request->query('delivery_to', '');

        // Fresh visit (no date params at all): focus on the NEXT delivery day
        // that has orders. Explicitly cleared inputs (params present but empty)
        // mean "all dates".
        if (! $request->has('delivery_from') && ! $request->has('delivery_to')) {
            $next = $this->nextDeliveryDayWithOrders();
            if ($next !== null) {
                $from = $to = $next;
            }
        }

        // '' = all, 'b2c' = private shop customers, 'b2b' = business —
        // admin-created business shop orders AND restaurant orders are the
        // same kind of customer, so they share one filter value.
        $type = (string) $request->query('type', '');
        if (! in_array($type, ['', 'b2c', 'b2b'], true)) {
            $type = '';
        }

        return ['status' => $status, 'type' => $type, 'delivery_from' => $from, 'delivery_to' => $to];
    }

    /**
     * Common filter used by the list page and the export. Encapsulates the
     * "finalized" pseudo-status and the delivery-date range.
     *
     * @param  array{status: string, delivery_from: string, delivery_to: string}  $filter
     * @return Builder<ShopOrder>
     */
    private function filteredOrdersQuery(array $filter): Builder
    {
        $statusEnum = ShopOrderStatus::tryFrom($filter['status']);

        return ShopOrder::query()
            ->when($filter['status'] === self::FILTER_FINALIZED, fn ($q) => $q->where(function ($inner) {
                $inner->whereIn('status', [
                    ShopOrderStatus::Paid->value,
                    ShopOrderStatus::Fulfilled->value,
                ])->orWhere(function ($pickup) {
                    $pickup->where('status', ShopOrderStatus::PendingPayment->value)
                        ->where('payment_method', 'in_person');
                });
            }))
            ->when($statusEnum, fn ($q) => $q->where('status', $statusEnum))
            ->when($filter['type'] ?? '', fn ($q, $type) => match ($type) {
                'b2c' => $q->where('customer_type', CustomerType::Private->value),
                'b2b' => $q->where('customer_type', CustomerType::Business->value),
                default => $q,
            })
            ->when($filter['delivery_from'] !== '', fn ($q) => $q->whereDate('expected_delivery_date', '>=', $filter['delivery_from']))
            ->when($filter['delivery_to'] !== '', fn ($q) => $q->whereDate('expected_delivery_date', '<=', $filter['delivery_to']));
    }

    public function show(ShopOrder $shopOrder): View
    {
        $shopOrder->load('items.product.translations', 'items.product.producer.translations');

        return view('admin.shop-orders.show', [
            'order' => $shopOrder,
        ]);
    }

    /**
     * WYSIWYG receipt editor: a page that looks like the Kaufbeleg where
     * every text is an input. Display-only — money edits go through the
     * order editor, which tracks paid/open amounts. Available for every
     * shop order; mirrors of restaurant orders route to their invoice
     * editor instead (their document is the Rechnung).
     */
    public function receiptEditor(ShopOrder $shopOrder): View|RedirectResponse
    {
        if ($shopOrder->restaurant_order_id !== null) {
            return redirect()->route('app.admin.shop-orders.invoice-editor', $shopOrder->restaurant_order_id);
        }

        $shopOrder->load('items.product.translations', 'items.product.producer.translations');

        if ($shopOrder->fulfillment_mode === 'delivery') {
            $fulfillmentDefault = 'Lieferung: '.trim($shopOrder->delivery_street.', '.$shopOrder->delivery_postal_code.' '.$shopOrder->delivery_city, ', ');
            $deliveryAddress = trim($shopOrder->delivery_street.', '.$shopOrder->delivery_postal_code.' '.$shopOrder->delivery_city, ', ');
        } else {
            $pickup = ShopOrderService::pickupLocation($shopOrder->pickup_location);
            $deliveryAddress = $pickup !== null
                ? trim(($pickup['street'] ?? '').', '.trim(($pickup['postal_code'] ?? '').' '.($pickup['city'] ?? '')), ', ')
                : (string) $shopOrder->pickup_location;
            $fulfillmentDefault = 'Abholung: '.$deliveryAddress;
        }

        $paymentDefault = match (true) {
            $shopOrder->payment_method === 'in_person' => 'Barzahlung / Kartenzahlung vor Ort',
            $shopOrder->paid_at !== null && $shopOrder->outstandingAmountEur() >= 0.01 => 'Online-Zahlung, Restbetrag vor Ort',
            $shopOrder->paid_at !== null && $shopOrder->outstandingAmountEur() <= -0.01 => 'Online-Zahlung, Erstattung vor Ort',
            default => 'Online-Zahlung',
        };

        $defaults = [
            'seller_name' => (string) config('shop.company_name'),
            'seller_register' => (string) config('shop.company_register'),
            'seller_vat_id' => config('shop.company_vat_id') ? 'Umsatzsteuer-ID: '.config('shop.company_vat_id') : '',
            'seller_address' => trim((string) config('shop.company_street').', '.(string) config('shop.company_postal_city'), ', '),
            'seller_email' => (string) config('shop.company_email'),
            'recipient_name' => (string) $shopOrder->name,
            'recipient_email' => (string) ($shopOrder->email ?? ''),
            'recipient_phone' => (string) ($shopOrder->phone ?? ''),
            'recipient_fulfillment' => $fulfillmentDefault,
            'delivery_line_label' => 'Lieferung '.$deliveryAddress,
            'payment_text' => $paymentDefault,
            'questions_text' => trim((config('shop.company_email') ? 'Email '.config('shop.company_email') : '')
                .(config('shop.company_phone') ? "\noder anrufen unter ".config('shop.company_phone') : '')),
            'notes_text' => 'Alle Preise sind Bruttopreise und enthalten die gesetzliche Mehrwertsteuer.'
                .((float) $shopOrder->total_amount_eur <= 250 ? ' Dieser Beleg gilt als Kleinbetragsrechnung gem. § 33 UStDV.' : ''),
        ];

        $values = [];
        foreach ($defaults as $key => $default) {
            $values[$key] = $shopOrder->receiptOverride($key) ?? $default;
        }

        return view('admin.shop-orders.receipt-editor', [
            'order' => $shopOrder,
            'values' => $values,
            'vatByRate' => ShopOrderService::vatByRate($shopOrder),
        ]);
    }

    /** Persist the receipt editor's display overrides + per-line names. */
    public function saveReceiptEditor(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $rules = [
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.display_name' => ['nullable', 'string', 'max:160'],
            'overrides' => ['nullable', 'array'],
        ];
        foreach (ShopOrderService::RECEIPT_OVERRIDE_KEYS as $key => $maxLength) {
            $rules["overrides.$key"] = ['nullable', 'string', "max:$maxLength"];
        }
        $validated = $request->validate($rules);

        $this->service->saveReceiptOverrides(
            $shopOrder,
            (array) ($validated['overrides'] ?? []),
            (array) ($validated['items'] ?? []),
        );

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'receipt_edit',
            fromState: $shopOrder->status->value,
            toState: $shopOrder->status->value,
            reason: null,
            metadata: [
                'channel' => 'admin',
                'override_keys' => array_keys((array) ($shopOrder->refresh()->receipt_overrides ?? [])),
            ],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.shop-orders.receipt-editor', $shopOrder)
            ->with('status', __('shop.admin.receipt_editor_saved_flash'));
    }

    /**
     * Edit form for an existing order — the manual-order form prefilled from
     * the stored lines. Every prefilled line is marked "touched" so the
     * debounced catalog quote never overwrites stored (possibly negotiated)
     * prices; the admin re-triggers autofill per line by changing it.
     */
    public function edit(ShopOrder $shopOrder): View|RedirectResponse
    {
        if (! $shopOrder->isEditableByAdmin()) {
            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('error', __('shop.admin.edit_blocked_online_pending'));
        }

        $shopOrder->load('items');

        $preselectedItems = $shopOrder->items->map(fn ($item): array => [
            'custom' => $item->product_id === null,
            'grade_key' => $item->product_id !== null
                ? ($item->variant_id !== null ? $item->product_id.':'.$item->variant_id : (string) $item->product_id)
                : '',
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'name' => (string) ($item->name_snapshot ?? ''),
            'unit' => (string) ($item->unit ?? ''),
            'quantity' => (float) $item->quantity,
            'line_total_eur' => (float) $item->line_total_eur,
            'vat_rate_percent' => (int) $item->vat_rate_snapshot_percent,
            'lineTouched' => true,
            'vatTouched' => true,
        ])->values()->all();

        return view('admin.shop-orders.edit', [
            'order' => $shopOrder,
            'preselectedItems' => $preselectedItems,
            'pickupLocations' => PickupLocations::all(),
            'availableProductsUrl' => route('app.admin.shop-orders.available-products'),
            'quoteUrl' => route('app.admin.shop-orders.quote'),
        ]);
    }

    /**
     * Persist an admin edit. Money history stays intact: what was collected
     * remains in paid_amount_eur, and any difference to the new total shows
     * up as an open remainder (or refund due) on the order.
     */
    public function update(AdminUpdateShopOrderRequest $request, ShopOrder $shopOrder): RedirectResponse
    {
        $snapshot = fn (ShopOrder $o): array => [
            'items_total_eur' => (float) $o->items_total_eur,
            'delivery_fee_eur' => (float) $o->delivery_fee_eur,
            'total_amount_eur' => (float) $o->total_amount_eur,
            'expected_delivery_date' => $o->expected_delivery_date->toDateString(),
            'fulfillment_mode' => $o->fulfillment_mode,
            'item_count' => $o->items()->count(),
        ];
        $before = $snapshot($shopOrder);

        $shopOrder = $this->service->updateOrder($shopOrder, $request->validated());

        $after = $snapshot($shopOrder);
        $changed = array_keys(array_filter($before, fn ($value, $key): bool => $after[$key] !== $value, ARRAY_FILTER_USE_BOTH));

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'update',
            fromState: $shopOrder->status->value,
            toState: $shopOrder->status->value,
            reason: null,
            metadata: [
                'channel' => 'admin',
                'before' => $before,
                'after' => $after,
                'changed' => $changed,
                'paid_amount_eur' => $shopOrder->paid_amount_eur !== null ? (float) $shopOrder->paid_amount_eur : null,
                'outstanding_eur' => $shopOrder->outstandingAmountEur(),
            ],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.order_updated_success'));
    }

    /**
     * Record that the open difference between collected and owed was settled
     * in person — either the remainder was collected, or the overpayment was
     * refunded. Keeps a signed trace on the order so "was refunded" stays
     * visible after the fact.
     */
    public function settleOutstanding(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        if (! $shopOrder->hasOpenAmount()) {
            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('error', __('shop.admin.errors.nothing_to_settle'));
        }

        $outstanding = $shopOrder->outstandingAmountEur();
        $direction = $outstanding > 0 ? 'collected' : 'refunded';

        $shopOrder->forceFill([
            'paid_amount_eur' => $shopOrder->total_amount_eur,
            'outstanding_settled_amount_eur' => $outstanding,
            'outstanding_settled_at' => now(),
            'outstanding_settled_by_user_id' => (int) $request->user()->id,
        ])->save();

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'settle_outstanding',
            fromState: $shopOrder->status->value,
            toState: $shopOrder->status->value,
            reason: null,
            metadata: [
                'channel' => 'admin',
                'amount_eur' => $outstanding,
                'direction' => $direction,
            ],
            actorUserId: (int) $request->user()->id,
        );

        $amountLabel = number_format(abs($outstanding), 2, ',', '.').' €';

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', $direction === 'collected'
                ? __('shop.admin.flash_settled_collected', ['amount' => $amountLabel])
                : __('shop.admin.flash_settled_refunded', ['amount' => $amountLabel]));
    }

    public function markPaid(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        if ($shopOrder->status === ShopOrderStatus::PendingPayment) {
            $shopOrder->forceFill([
                'status' => ShopOrderStatus::Paid,
                'paid_at' => now(),
                'paid_amount_eur' => $shopOrder->total_amount_eur,
                'marked_paid_by_admin_at' => now(),
            ])->save();

            AuditLogger::log(
                entityType: 'shop_order',
                entityId: $shopOrder->id,
                action: 'mark_paid',
                fromState: ShopOrderStatus::PendingPayment->value,
                toState: ShopOrderStatus::Paid->value,
                reason: null,
                metadata: ['channel' => 'admin'],
                actorUserId: (int) $request->user()->id,
            );
        }

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.flash_marked_paid', ['id' => $shopOrder->id]));
    }

    public function markCompleted(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $now = now();
        $from = $shopOrder->status->value;
        $payload = [
            'status' => ShopOrderStatus::Fulfilled,
            'fulfilled_at' => $now,
        ];

        // In-person order completed in one click: flip paid + fulfilled at once.
        if ($shopOrder->status === ShopOrderStatus::PendingPayment) {
            $payload['paid_at'] = $now;
            $payload['paid_amount_eur'] = $shopOrder->total_amount_eur;
            $payload['marked_paid_by_admin_at'] = $now;
        }

        $shopOrder->forceFill($payload)->save();

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'mark_completed',
            fromState: $from,
            toState: ShopOrderStatus::Fulfilled->value,
            reason: null,
            metadata: ['channel' => 'admin'],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.flash_marked_completed', ['id' => $shopOrder->id]));
    }

    /**
     * Set / clear the delivery (or pickup) time window straight from the
     * orders list. When a concrete window is published (and actually
     * changed) the customer is emailed the new time; clearing it, or saving
     * the same window again, sends nothing.
     */
    public function updateDeliveryWindow(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $validated = $request->validate([
            'window_from' => ['nullable', 'date_format:H:i'],
            'window_to' => ['nullable', 'date_format:H:i', 'after:window_from'],
        ]);

        $from = $validated['window_from'] ?? null;
        $to = $validated['window_to'] ?? null;
        // A window needs both ends; a lone bound is treated as "not set".
        if ($from === null || $to === null) {
            $from = $to = null;
        }

        $previous = $shopOrder->deliveryWindowLabel();

        $shopOrder->forceFill([
            'delivery_window_from' => $from,
            'delivery_window_to' => $to,
            'delivery_window_set_at' => $from !== null ? now() : null,
        ])->save();

        $changed = $shopOrder->deliveryWindowLabel() !== $previous;
        $flashKey = 'shop.admin.flash_window_cleared';

        if ($shopOrder->hasDeliveryWindow() && $changed) {
            $this->queueWindowMail($shopOrder);
            $flashKey = 'shop.admin.flash_window_saved';
        } elseif ($shopOrder->hasDeliveryWindow()) {
            $flashKey = 'shop.admin.flash_window_unchanged';
        }

        return redirect()
            ->route('app.admin.shop-orders.index', $request->only('status', 'delivery_from', 'delivery_to', 'page'))
            ->with('status', __($flashKey, ['id' => $shopOrder->id]));
    }

    /**
     * Queue the "window set" customer email, swallowing mail-driver failures
     * so a transient outage never blocks the admin save. Mirrors the shop
     * confirmation-mail try/catch.
     */
    private function queueWindowMail(ShopOrder $order): void
    {
        try {
            Mail::to($order->email)->queue(new ShopOrderWindowMail($order));
        } catch (Throwable $e) {
            report($e);
            SumUpCheckoutLogger::warning('Shop order: failed to queue delivery-window email.', [
                'shop_order_id' => $order->id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }
    }

    /**
     * Permanently delete a shop order (cascading to its items). Mirrors
     * {@see SpargelAdminController::destroy()} but writes an audit row first
     * — once the order row is gone, the audit log is its only trace. Deleted
     * orders no longer commit stock, so inventory is released automatically.
     */
    public function destroy(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $id = $shopOrder->id;

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $id,
            action: 'delete',
            fromState: $shopOrder->status->value,
            toState: null,
            reason: null,
            metadata: ['channel' => 'admin'],
            actorUserId: (int) $request->user()->id,
        );

        // Release any finalized coupon slot before the row (and its FK) is
        // gone — voidRedemption looks the redemption up by shop_order_id.
        app(CouponService::class)->voidRedemption($shopOrder);

        $shopOrder->delete();

        return redirect()
            ->route('app.admin.shop-orders.index')
            ->with('status', __('shop.admin.flash_deleted', ['id' => $id]));
    }

    /**
     * Refund a paid shop order. Asks SumUp first — only flip local status to
     * Refunded once SumUp confirms. Externally-paid orders (no transaction id)
     * are recorded as a manual refund without a provider call. Mirrors
     * {@see SpargelAdminController::refund()}.
     */
    public function refund(Request $request, ShopOrder $shopOrder, SumUpService $sumUpService): RedirectResponse
    {
        if ($shopOrder->status !== ShopOrderStatus::Paid) {
            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('status', __('shop.admin.flash_refund_not_allowed', ['id' => $shopOrder->id]));
        }

        $reason = (string) $request->input('refund_reason', '');
        // Refund what was actually collected: after an admin edit the total
        // can differ from the captured SumUp charge.
        $amount = (float) ($shopOrder->effectivePaidAmountEur() ?? $shopOrder->total_amount_eur);
        $actorId = (int) $request->user()->id;
        $transactionId = (string) ($shopOrder->sumup_payment_id ?? '');

        // No SumUp transaction id ⇒ externally-paid (in-person) order; record
        // a manual refund without calling SumUp.
        if ($transactionId === '') {
            $shopOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);
            AuditLogger::log(
                entityType: 'shop_order',
                entityId: $shopOrder->id,
                action: 'refund',
                fromState: ShopOrderStatus::Paid->value,
                toState: ShopOrderStatus::Refunded->value,
                reason: $reason !== '' ? $reason : null,
                metadata: array_filter([
                    'amount' => (string) $amount,
                    'currency' => 'EUR',
                    'provider_call' => 'no_transaction_id',
                ], static fn ($v) => $v !== null),
                actorUserId: $actorId,
            );

            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('status', __('shop.admin.flash_refunded_local_only', ['id' => $shopOrder->id]));
        }

        // Standard path: ask SumUp first, only promote status on success.
        try {
            $sumUpService->refund($transactionId, $amount);
        } catch (Throwable $e) {
            $rawError = SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage());
            $shopOrder->recordRefundAttemptFailed($rawError);

            SumUpCheckoutLogger::warning('Shop order refund: SumUp provider call failed — status NOT changed.', [
                'shop_order_id' => $shopOrder->id,
                'sumup_payment_id' => $transactionId,
                'exception_class' => $e::class,
                'error' => $rawError,
                'refund_attempts' => (int) $shopOrder->refund_attempts,
            ]);

            AuditLogger::log(
                entityType: 'shop_order',
                entityId: $shopOrder->id,
                action: 'refund_attempt_failed',
                fromState: ShopOrderStatus::Paid->value,
                toState: ShopOrderStatus::Paid->value,
                reason: $reason !== '' ? $reason : null,
                metadata: array_filter([
                    'amount' => (string) $amount,
                    'currency' => 'EUR',
                    'error' => $rawError,
                    'attempts' => (string) $shopOrder->refund_attempts,
                ], static fn ($v) => $v !== null),
                actorUserId: $actorId,
            );

            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('status', __('shop.admin.flash_refund_provider_failed', ['id' => $shopOrder->id]));
        }

        // SumUp confirmed → safe to flip status.
        $shopOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'refund',
            fromState: ShopOrderStatus::Paid->value,
            toState: ShopOrderStatus::Refunded->value,
            reason: $reason !== '' ? $reason : null,
            metadata: array_filter([
                'amount' => (string) $amount,
                'currency' => 'EUR',
                'provider_call' => 'ok',
            ], static fn ($v) => $v !== null),
            actorUserId: $actorId,
        );

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.flash_refunded', ['id' => $shopOrder->id]));
    }
}
