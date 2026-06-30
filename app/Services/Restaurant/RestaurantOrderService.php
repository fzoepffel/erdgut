<?php

namespace App\Services\Restaurant;

use App\Enums\CustomerType;
use App\Enums\RestaurantOrderItemTrack;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopStockAudience;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     * @param  array{
     *     items: array<int, array{product_id:int, variant_id:?int, quantity:float}>,
     *     expected_delivery_date: string,
     *     delivery_street: string, delivery_postal_code: string, delivery_city: string,
     *     delivery_notes?: ?string, locale?: ?string
     * }  $payload
     */
    public function submit(User $user, array $payload): RestaurantOrder
    {
        $date = Carbon::parse($payload['expected_delivery_date'])->startOfDay();
        $lines = $this->mergeLines($payload['items'] ?? []);
        if ($lines === []) {
            throw new InvalidArgumentException('A restaurant order needs at least one line.');
        }

        $products = Product::query()
            ->whereIn('id', array_column($lines, 'product_id'))
            ->visibleInCatalog()
            ->with(['priceRules', 'variants'])
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($user, $payload, $date, $lines, $products): RestaurantOrder {
            $inventory = app(InventoryService::class);

            $order = RestaurantOrder::create([
                'user_id' => $user->id,
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

                // Lock the product row so a concurrent submit can't read the
                // same remaining and over-reserve.
                Product::query()->lockForUpdate()->find($product->id);
                $remaining = $inventory->availableOnDate($product, $date, ShopStockAudience::BusinessTrade, $variantId ?? 0);

                // Whole-line: reserve only when the full quantity fits in stock,
                // otherwise the line is a request (reserves nothing).
                $track = ($remaining + 1e-6 >= $qty)
                    ? RestaurantOrderItemTrack::Reserved
                    : RestaurantOrderItemTrack::Requested;

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
                    'unit_price_snapshot_eur' => $unitGross,
                    'vat_rate_snapshot_percent' => $vatRate,
                    'line_total_eur' => $lineGross,
                    'vat_amount_eur' => $vatAmount,
                    'price_rule_id' => $resolved->ruleId,
                ]);

                $totalGross += $lineGross;
                $totalVat += $vatAmount;
            }

            if ($order->items()->count() === 0) {
                throw new InvalidArgumentException('None of the submitted lines are orderable.');
            }

            $order->forceFill([
                'items_total_gross_eur' => round($totalGross, 2),
                'vat_total_eur' => round($totalVat, 2),
                'items_total_net_eur' => round($totalGross - $totalVat, 2),
            ])->save();

            return $order;
        });
    }

    /** Cancel a submitted request (owner action). Reserved lines release stock automatically. */
    public function cancel(RestaurantOrder $order): void
    {
        $order->forceFill(['status' => RestaurantOrderStatus::Cancelled])->save();
    }

    /**
     * Admin transitions. Each is a guarded single status write — InventoryService
     * recomputes reservations from the status, so no stock mutation is needed
     * (declining a request releases its hold; confirming/fulfilling keeps it).
     * Returns whether the transition applied.
     */
    public function confirm(RestaurantOrder $order): bool
    {
        return $this->transition($order, RestaurantOrderStatus::Submitted, RestaurantOrderStatus::Confirmed);
    }

    public function decline(RestaurantOrder $order): bool
    {
        return $this->transition($order, RestaurantOrderStatus::Submitted, RestaurantOrderStatus::Declined);
    }

    public function fulfil(RestaurantOrder $order): bool
    {
        return $this->transition($order, RestaurantOrderStatus::Confirmed, RestaurantOrderStatus::Fulfilled);
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
            $key = $productId.':'.($variantId ?? 0);
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $qty;
            } else {
                $merged[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $qty];
            }
        }

        return array_values($merged);
    }
}
