<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Exports\ShopOrdersExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SpargelAdminController;
use App\Http\Requests\Admin\AdminCreateShopOrderRequest;
use App\Mail\ShopOrderWindowMail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopOrder;
use App\Services\Admin\InventoryService;
use App\Services\Shop\CouponService;
use App\Services\Shop\InsufficientStockException;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopOrderService;
use App\Support\AuditLogger;
use App\Support\PickupLocations;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

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
        foreach ($this->service->availableProductsForDate($date, $audience) as $row) {
            /** @var Product $product */
            $product = $row['product'];
            $product->loadMissing('priceRules', 'variants.translations');
            $variants = $product->activeVariants();

            // Default grade: present when the product has no named variants,
            // or when the default itself has stock that day.
            $defaultRemaining = $variants->isEmpty()
                ? (float) $row['remaining']
                : (float) $this->inventory->remainingForGrade($product, $date, null);
            if ($variants->isEmpty() || $defaultRemaining > 0) {
                $grades[] = $this->buildGrade($product, null, $defaultRemaining, $date, $customerType);
            }

            // Named grades.
            foreach ($variants as $variant) {
                $remaining = (float) $this->inventory->remainingForGrade($product, $date, $variant->id);
                if ($remaining <= 0) {
                    continue;
                }
                $grades[] = $this->buildGrade($product, $variant, $remaining, $date, $customerType);
            }
        }

        return response()->json($grades);
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

        return $product->unit->formatPrice($unitPriceEur, $displayUnit);
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

        $quote = $this->service->quote(
            $items,
            $fulfillmentMode,
            $normalizedCoupon,
            null,
            $quoteDate,
            $customerType,
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
        return view('admin.shop-orders.create', [
            'pickupLocations' => PickupLocations::all(),
            'availableProductsUrl' => route('app.admin.shop-orders.available-products'),
            'quoteUrl' => route('app.admin.shop-orders.quote'),
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
     * Default filter: only "finalized" orders — confirmed sales the team
     * actually has to act on. That means paid orders, already-fulfilled
     * orders, and the in-person ones still awaiting payment at pickup
     * ("Zahlung bei Abholung"). Abandoned online checkouts, failed,
     * cancelled and refunded orders are hidden unless explicitly chosen.
     */
    private const FILTER_FINALIZED = 'finalized';

    public function index(Request $request): View
    {
        $filter = $this->resolveFilter($request);

        $orders = $this->filteredOrdersQuery($filter)
            ->with('items.product.translations')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.shop-orders.index', [
            'orders' => $orders,
            'statuses' => ShopOrderStatus::cases(),
            'status' => $filter['status'],
            'deliveryFrom' => $filter['delivery_from'],
            'deliveryTo' => $filter['delivery_to'],
        ]);
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

        $filename = 'erdgut-bestellungen-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ShopOrdersExport($orders, $filter), $filename);
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

        return ['status' => $status, 'delivery_from' => $from, 'delivery_to' => $to];
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

    public function markPaid(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        if ($shopOrder->status === ShopOrderStatus::PendingPayment) {
            $shopOrder->forceFill([
                'status' => ShopOrderStatus::Paid,
                'paid_at' => now(),
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
        $amount = (float) $shopOrder->total_amount_eur;
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
