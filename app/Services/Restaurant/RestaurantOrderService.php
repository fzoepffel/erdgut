<?php

namespace App\Services\Restaurant;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\RestaurantOrderItemTrack;
use App\Enums\RestaurantOrderKind;
use App\Enums\RestaurantOrderLineStatus;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopClosureService;
use App\Services\Shop\ShopOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Persists a restaurant (B2B) order/request. No payment: in-stock lines are
 * hard-reserved on submit (track = reserved → counted by InventoryService),
 * out-of-stock lines become non-reserving requests. Prices are snapshotted at
 * the business rate. Mirrors {@see ShopOrderService::createOrder}'s row-locked
 * recheck so concurrent submits can't double-book.
 */
class RestaurantOrderService
{
    public function __construct(private readonly PriceResolver $priceResolver) {}

    /**
     * Shop checkout: every line must be a catalog product fully in stock —
     * hard-reserved and confirmed the moment it's placed (kind=order, born
     * Confirmed). Over-stock → ValidationException (the shop never silently
     * turns an order into a request); free-form lines are rejected.
     */
    public function submitOrder(User $user, array $payload): RestaurantOrder
    {
        return $this->create($user, $payload, 'order');
    }

    /**
     * Anfragen area: season items, over-stock amounts and free-form wishes —
     * any quantity, nothing reserved, all lines pending until the admin
     * accepts/declines them (kind=request, born Submitted).
     */
    public function submitRequest(User $user, array $payload): RestaurantOrder
    {
        return $this->create($user, $payload, 'request');
    }

    /**
     * Standing-order materializer: weekly deliveries the admin already approved.
     * Always kind=order + born Confirmed; a line reserves when that week's stock
     * covers it, otherwise it stays requested-track (admin sources it) but is
     * still auto-confirmed — the recurrence itself was the approval.
     */
    public function submitMaterialized(User $user, array $payload): RestaurantOrder
    {
        return $this->create($user, $payload, 'materialized');
    }

    /**
     * @param  array{
     *     items: array<int, array{product_id:int, variant_id:?int, quantity:float}>,
     *     expected_delivery_date: string,
     *     delivery_street: string, delivery_postal_code: string, delivery_city: string,
     *     delivery_notes?: ?string, locale?: ?string
     * }  $payload
     * @param  'order'|'request'|'materialized'  $mode
     */
    private function create(User $user, array $payload, string $mode): RestaurantOrder
    {
        $date = Carbon::parse($payload['expected_delivery_date'])->startOfDay();

        // Catalog lines carry a product_id; free-form "special request" lines
        // carry only a name. They take different paths (custom lines never
        // reserve and aren't priced at submit), so split before merging —
        // mergeLines() drops product-less lines.
        [$productItems, $customItems] = $this->splitLines($payload['items'] ?? []);
        if ($mode === 'order' && $customItems !== []) {
            throw ValidationException::withMessages(['items' => __('restaurant.order_no_custom_lines')]);
        }
        $lines = $this->mergeLines($productItems);
        if ($lines === [] && $customItems === []) {
            throw new InvalidArgumentException('A restaurant order needs at least one line.');
        }

        $products = Product::query()
            ->whereIn('id', array_column($lines, 'product_id'))
            ->visibleInCatalog()
            ->with(['priceRules', 'variants'])
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($user, $payload, $date, $lines, $customItems, $products, $mode): RestaurantOrder {
            $inventory = app(InventoryService::class);

            $order = RestaurantOrder::create([
                'user_id' => $user->id,
                'kind' => $mode === 'request' ? RestaurantOrderKind::Request : RestaurantOrderKind::Order,
                'status' => RestaurantOrderStatus::Submitted,
                'expected_delivery_date' => $date->toDateString(),
                'delivery_street' => $payload['delivery_street'],
                'delivery_postal_code' => $payload['delivery_postal_code'],
                'delivery_city' => $payload['delivery_city'],
                'delivery_notes' => $payload['delivery_notes'] ?? null,
                'locale' => $payload['locale'] ?? $user->locale ?? app()->getLocale(),
                'submitted_at' => now(),
                'items_total_net_eur' => 0,
                'items_total_gross_eur' => 0,
                'vat_total_eur' => 0,
            ]);

            $totalGross = 0.0;
            $totalVat = 0.0;

            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                if ($product === null) {
                    continue; // not orderable (unpublished/hidden) — silently skip
                }

                $variantId = $line['variant_id'];
                $variant = $variantId !== null ? $product->variants->firstWhere('id', $variantId) : null;
                if ($variantId !== null && ($variant === null || ! $variant->is_active)) {
                    continue;
                }

                $qty = (float) $line['quantity'];

                if ($mode === 'request') {
                    // Requests never reserve — no lock/stock check needed.
                    $track = RestaurantOrderItemTrack::Requested;
                    $lineStatus = RestaurantOrderLineStatus::Requested;
                } else {
                    // Lock the product row so a concurrent submit can't read the
                    // same remaining and over-reserve.
                    Product::query()->lockForUpdate()->find($product->id);
                    $remaining = $inventory->availableOnDate($product, $date, ShopStockAudience::BusinessTrade, $variantId ?? 0);
                    $fits = $remaining + 1e-6 >= $qty;

                    if ($mode === 'order' && ! $fits) {
                        // The shop sells only what's in stock — never silently
                        // convert an order line into a request.
                        throw ValidationException::withMessages([
                            'items' => __('restaurant.order_line_exceeds_stock', ['name' => $product->name]),
                        ]);
                    }

                    $track = $fits ? RestaurantOrderItemTrack::Reserved : RestaurantOrderItemTrack::Requested;
                    // Order + materialized lines are firm immediately (the
                    // materializer's approval is the recurrence itself).
                    $lineStatus = RestaurantOrderLineStatus::Confirmed;
                }

                $resolved = $this->priceResolver->resolve($product, $date, CustomerType::Business, $qty, $variantId, $variant);
                $unitGross = $resolved->unitPriceEur; // gross per canonical unit
                $lineGross = round($unitGross * $qty, 2);
                $vatRate = (int) $product->vat_rate_percent;
                $vatAmount = ShopOrderService::vatAmountFromGross($lineGross, $vatRate);

                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'variant_name_snapshot' => $variant?->localizedName(),
                    'quantity' => $qty,
                    'unit' => $product->unit?->value,
                    'track' => $track,
                    'line_status' => $lineStatus,
                    'recurrence' => $line['recurrence'] ?? 'one_time',
                    'recurrence_until' => $line['until'] ?? null,
                    'unit_price_snapshot_eur' => $unitGross,
                    'vat_rate_snapshot_percent' => $vatRate,
                    'line_total_eur' => $lineGross,
                    'vat_amount_eur' => $vatAmount,
                    'price_rule_id' => $resolved->ruleId,
                ]);

                $totalGross += $lineGross;
                $totalVat += $vatAmount;
            }

            // Free-form special requests: no catalog product, no stock hold, no
            // price at submit (the admin prices them at the invoice step). Always
            // a "request".
            foreach ($customItems as $custom) {
                $name = trim((string) ($custom['name'] ?? ''));
                $qty = (float) ($custom['quantity'] ?? 0);
                if ($name === '' || $qty <= 0) {
                    continue;
                }
                [$recurrence, $until] = $this->normalizeRecurrence($custom);

                $order->items()->create([
                    'product_id' => null,
                    'variant_id' => null,
                    'variant_name_snapshot' => null,
                    'name_snapshot' => $name,
                    'quantity' => $qty,
                    'unit' => trim((string) ($custom['unit'] ?? '')) ?: null,
                    'track' => RestaurantOrderItemTrack::Requested,
                    // Materialized recurring custom lines were approved with the
                    // recurrence; fresh request lines await the admin.
                    'line_status' => $mode === 'materialized'
                        ? RestaurantOrderLineStatus::Confirmed
                        : RestaurantOrderLineStatus::Requested,
                    'recurrence' => $recurrence,
                    'recurrence_until' => $until,
                    'unit_price_snapshot_eur' => 0,
                    'vat_rate_snapshot_percent' => (int) config('shop.vat_rate_percent', 7),
                    'line_total_eur' => 0,
                    'vat_amount_eur' => 0,
                    'price_rule_id' => null,
                ]);
            }

            if ($order->items()->count() === 0) {
                throw new InvalidArgumentException('None of the submitted lines are orderable.');
            }

            // Orders (shop + materialized weekly deliveries) are firm the moment
            // they exist; requests wait for the admin to accept/decline lines.
            $status = $mode === 'request'
                ? RestaurantOrderStatus::Submitted
                : RestaurantOrderStatus::Confirmed;

            $order->forceFill([
                'status' => $status,
                'items_total_gross_eur' => round($totalGross, 2),
                'vat_total_eur' => round($totalVat, 2),
                'items_total_net_eur' => round($totalGross - $totalVat, 2),
            ])->save();

            if ($status === RestaurantOrderStatus::Confirmed) {
                $this->ensureShopOrderMirror($order);
            }

            return $order;
        });
    }

    /** Cancel a submitted request (owner action). Reserved lines release stock automatically. */
    public function cancel(RestaurantOrder $order): void
    {
        $order->forceFill(['status' => RestaurantOrderStatus::Cancelled])->save();
    }

    /**
     * Admin: accept the whole order — every still-pending (on-request/special)
     * line becomes a firm line and the order is confirmed. In-stock lines were
     * already confirmed at submit. Only from Submitted.
     */
    public function confirm(RestaurantOrder $order): bool
    {
        if ($order->status !== RestaurantOrderStatus::Submitted) {
            return false;
        }

        $order->items()
            ->where('line_status', RestaurantOrderLineStatus::Requested->value)
            ->update(['line_status' => RestaurantOrderLineStatus::Confirmed->value]);
        $order->forceFill(['status' => RestaurantOrderStatus::Confirmed])->save();

        $this->activateStandingIfPending($order);
        $this->ensureShopOrderMirror($order);

        return true;
    }

    /**
     * Admin: reject the whole order — all lines declined, order declined,
     * reservations released (status leaves the reserving set). Only from Submitted.
     */
    public function decline(RestaurantOrder $order): bool
    {
        if ($order->status !== RestaurantOrderStatus::Submitted) {
            return false;
        }

        $order->items()->update(['line_status' => RestaurantOrderLineStatus::Declined->value]);
        $order->forceFill(['status' => RestaurantOrderStatus::Declined])->save();

        return true;
    }

    /**
     * Owner edit of a firm delivery ("this week 2 kg less"): change quantities
     * or drop lines on a confirmed, not-invoiced order while ordering for its
     * delivery day is still open. Catalog lines are stock-checked; the line's
     * own current quantity counts as available (its reservation is already in
     * the commitments). Line money recomputes from the snapshot unit price;
     * the order is marked adjusted so template changes never overwrite it.
     *
     * @param  list<array{id: int|string, quantity?: float|string, remove?: mixed}>  $lines
     */
    public function ownerUpdateLines(RestaurantOrder $order, array $lines): RestaurantOrder
    {
        if ($order->isRequest() && $order->status !== RestaurantOrderStatus::Confirmed) {
            throw ValidationException::withMessages(['lines' => __('restaurant.adjust_not_editable')]);
        }
        if ($order->status !== RestaurantOrderStatus::Confirmed || $order->isInvoiced()) {
            throw ValidationException::withMessages(['lines' => __('restaurant.adjust_not_editable')]);
        }
        if ($order->isLockedForChanges(app(ShopClosureService::class))) {
            throw ValidationException::withMessages(['lines' => __('restaurant.changes_locked')]);
        }

        $date = $order->expected_delivery_date->copy();

        return DB::transaction(function () use ($order, $lines, $date): RestaurantOrder {
            $items = $order->items()->lockForUpdate()->with('product')->get()->keyBy('id');
            $byId = collect($lines)->keyBy(fn ($l) => (int) ($l['id'] ?? 0));

            $surviving = 0;
            foreach ($items as $item) {
                if ($item->isDeclined()) {
                    continue;
                }
                $payload = $byId->get($item->id);
                if ($payload !== null && ! empty($payload['remove'])) {
                    continue;
                }
                $surviving++;
            }
            if ($surviving === 0) {
                throw ValidationException::withMessages(['lines' => __('restaurant.adjust_keep_one_line')]);
            }

            foreach ($items as $item) {
                $payload = $byId->get($item->id);
                if ($payload === null || $item->isDeclined()) {
                    continue;
                }

                if (! empty($payload['remove'])) {
                    $item->delete(); // reserved hold releases with the row

                    continue;
                }

                $qty = round((float) ($payload['quantity'] ?? $item->quantity), 4);
                if ($qty <= 0) {
                    throw ValidationException::withMessages(['lines' => __('restaurant.adjust_qty_invalid')]);
                }
                if (abs($qty - (float) $item->quantity) < 1e-9) {
                    continue;
                }

                // Stock check for catalog lines: the line may grow up to the
                // remaining stock PLUS what it already holds itself.
                if ($item->product_id !== null && $item->product !== null && $qty > (float) $item->quantity) {
                    $remaining = app(InventoryService::class)->availableOnDate(
                        $item->product,
                        $date,
                        ShopStockAudience::BusinessTrade,
                        (int) ($item->variant_id ?? 0),
                    );
                    if ($qty > $remaining + (float) $item->quantity + 1e-9) {
                        throw ValidationException::withMessages([
                            'lines' => __('restaurant.order_line_exceeds_stock', ['name' => $item->displayName()]),
                        ]);
                    }
                }

                $unit = (float) $item->unit_price_snapshot_eur;
                $gross = round($unit * $qty, 2);
                $rate = (int) $item->vat_rate_snapshot_percent;
                $net = round($gross * 100 / (100 + $rate), 2);
                $item->forceFill([
                    'quantity' => $qty,
                    'line_total_eur' => $gross,
                    'vat_amount_eur' => round($gross - $net, 2),
                ])->save();
            }

            app(RestaurantInvoiceService::class)->rerollTotals($order);
            $order->forceFill(['adjusted_at' => now()])->save();

            return $order->refresh();
        });
    }

    /** Admin: accept a single pending (on-request/special) line; settle the order. */
    public function acceptLine(RestaurantOrderItem $item): bool
    {
        if ($item->line_status !== RestaurantOrderLineStatus::Requested) {
            return false;
        }
        $item->forceFill(['line_status' => RestaurantOrderLineStatus::Confirmed])->save();
        $this->settle($item->restaurantOrder()->first());

        return true;
    }

    /** Admin: decline a single pending line; settle the order. Its hold (none) releases. */
    public function declineLine(RestaurantOrderItem $item): bool
    {
        if ($item->line_status !== RestaurantOrderLineStatus::Requested) {
            return false;
        }
        $item->forceFill(['line_status' => RestaurantOrderLineStatus::Declined])->save();
        $this->settle($item->restaurantOrder()->first());

        return true;
    }

    public function fulfil(RestaurantOrder $order): bool
    {
        return $this->transition($order, RestaurantOrderStatus::Confirmed, RestaurantOrderStatus::Fulfilled);
    }

    /**
     * Admin: cancel an order before it's delivered/invoiced (Submitted or the
     * auto-confirmed Confirmed). Releases reservations. Not once invoiced.
     */
    public function adminCancel(RestaurantOrder $order): bool
    {
        if ($order->isInvoiced()) {
            return false;
        }
        if (! in_array($order->status, [RestaurantOrderStatus::Submitted, RestaurantOrderStatus::Confirmed], true)) {
            return false;
        }

        $order->forceFill(['status' => RestaurantOrderStatus::Cancelled])->save();

        return true;
    }

    /**
     * After a per-line decision, advance a Submitted order once no line is still
     * pending: confirm it if any line survives, else decline the whole order.
     */
    private function settle(?RestaurantOrder $order): void
    {
        if ($order === null || $order->status !== RestaurantOrderStatus::Submitted) {
            return;
        }
        if ($order->hasPendingLines()) {
            return;
        }

        $anyLive = $order->items()->where('line_status', '!=', RestaurantOrderLineStatus::Declined->value)->exists();
        if ($anyLive) {
            $order->forceFill(['status' => RestaurantOrderStatus::Confirmed])->save();
            $this->activateStandingIfPending($order);
            $this->ensureShopOrderMirror($order);
        } else {
            $order->forceFill(['status' => RestaurantOrderStatus::Declined])->save();
        }
    }

    /**
     * Create — if not already present — a mirror ShopOrder for a firm restaurant
     * order so it lands in the unified admin shop-orders list and inherits the
     * shop-order editing surface. Idempotent: safe to call at every status
     * transition. Non-Declined item lines are copied with their money and unit
     * snapshots. The mirror carries restaurant_order_id back to the source,
     * which then serves as the "as-requested" history and is skipped by the
     * B2B revenue query (see AdminOperationsMetrics::b2bByDate).
     */
    public function ensureShopOrderMirror(RestaurantOrder $order): ?ShopOrder
    {
        if (! in_array($order->status, [RestaurantOrderStatus::Confirmed, RestaurantOrderStatus::Fulfilled], true)) {
            return null;
        }

        $existing = ShopOrder::query()->where('restaurant_order_id', $order->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($order): ShopOrder {
            $user = $order->user()->with('restaurantProfile')->first();
            $profile = $user?->restaurantProfile;
            $displayName = $profile?->business_name
                ?? $profile?->contact_name
                ?? $user?->name
                ?? '';

            $mirror = ShopOrder::create([
                'name' => $displayName,
                'email' => $user?->email ?? '',
                'phone' => null,
                'fulfillment_mode' => 'delivery',
                'expected_delivery_date' => $order->expected_delivery_date->toDateString(),
                'delivery_street' => $order->delivery_street,
                'delivery_postal_code' => $order->delivery_postal_code,
                'delivery_city' => $order->delivery_city,
                'delivery_notes' => $order->delivery_notes,
                'items_total_eur' => $order->items_total_gross_eur,
                'delivery_fee_eur' => 0,
                'delivery_fee_vat_rate_percent' => 0,
                'delivery_fee_vat_amount_eur' => 0,
                'total_amount_eur' => $order->items_total_gross_eur,
                'discount_eur' => 0,
                'payment_method' => 'in_person',
                'status' => $order->status === RestaurantOrderStatus::Fulfilled
                    ? ShopOrderStatus::Fulfilled->value
                    : ShopOrderStatus::PendingPayment->value,
                'payment_attempts' => 0,
                'is_test' => false,
                'order_source' => OrderSource::Admin->value,
                'customer_type' => CustomerType::Business->value,
                'restaurant_order_id' => $order->id,
                'locale' => $order->locale,
            ]);

            foreach ($order->items()->get() as $item) {
                if ($item->line_status === RestaurantOrderLineStatus::Declined) {
                    continue;
                }
                $mirror->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'variant_name_snapshot' => $item->variant_name_snapshot,
                    'name_snapshot' => $item->name_snapshot,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price_snapshot_eur' => $item->unit_price_snapshot_eur,
                    'price_rule_id' => $item->price_rule_id,
                    'line_total_eur' => $item->line_total_eur,
                    'vat_rate_snapshot_percent' => $item->vat_rate_snapshot_percent,
                    'vat_amount_eur' => $item->vat_amount_eur,
                ]);
            }

            return $mirror;
        });
    }

    /** Confirming the first recurring order activates the standing order. */
    private function activateStandingIfPending(RestaurantOrder $order): void
    {
        if ($order->source_standing_order_id === null) {
            return;
        }
        $standing = $order->standingOrder()->first();
        if ($standing !== null && $standing->isPending()) {
            app(RestaurantStandingOrderService::class)->activate($standing, null);
        }
    }

    private function transition(RestaurantOrder $order, RestaurantOrderStatus $from, RestaurantOrderStatus $to): bool
    {
        if ($order->status !== $from) {
            return false;
        }

        $order->forceFill(['status' => $to])->save();

        return true;
    }

    /**
     * Partition raw payload items into catalog lines (have a product_id) and
     * free-form custom lines (no product_id but a name). Lines with neither are
     * dropped.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function splitLines(array $items): array
    {
        $product = [];
        $custom = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0) {
                $product[] = $item;
            } elseif (trim((string) ($item['name'] ?? '')) !== '') {
                $custom[] = $item;
            }
        }

        return [$product, $custom];
    }

    /**
     * Sum duplicate product+grade lines so the sequential row-locked recheck
     * sees one quantity per grade.
     *
     * @param  array<int, array{product_id?:mixed, variant_id?:mixed, quantity?:mixed}>  $items
     * @return list<array{product_id:int, variant_id:?int, quantity:float}>
     */
    private function mergeLines(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (float) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' && $item['variant_id'] !== null
                ? (int) $item['variant_id']
                : null;
            [$recurrence, $until] = $this->normalizeRecurrence($item);
            $key = $productId.':'.($variantId ?? 0);
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $qty;
                // On a merge collision, a weekly line wins (keeps the schedule).
                if ($recurrence === 'weekly' && $merged[$key]['recurrence'] !== 'weekly') {
                    $merged[$key]['recurrence'] = 'weekly';
                    $merged[$key]['until'] = $until;
                }
            } else {
                $merged[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $qty, 'recurrence' => $recurrence, 'until' => $until];
            }
        }

        return array_values($merged);
    }

    /**
     * Normalize a raw payload line's recurrence to ('one_time'|'weekly', ?until).
     * `until` is kept only for weekly lines.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: string, 1: ?string}
     */
    private function normalizeRecurrence(array $item): array
    {
        $recurrence = ($item['recurrence'] ?? 'one_time') === 'weekly' ? 'weekly' : 'one_time';
        $until = $recurrence === 'weekly' && ! empty($item['until']) ? (string) $item['until'] : null;

        return [$recurrence, $until];
    }
}
