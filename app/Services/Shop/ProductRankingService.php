<?php

namespace App\Services\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ProductEngagementDaily;
use App\Models\ShopOrderItem;
use App\Support\BetaSampler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Orders the storefront product grid with a margin-per-impression
 * Thompson-sampling bandit.
 *
 * Each orderable product is an "arm". We model its add-to-cart rate
 * θ = P(add-to-cart | impression) as Beta(α, β) from time-decayed engagement
 * counters, and its realized value v = margin (or revenue) earned per
 * add-to-cart from time-decayed purchase history. A Thompson draw
 * `score = sample(θ) · v` is taken per product and the grid is sorted by it
 * descending. Drawing from the posterior is what produces exploration:
 * a product with few impressions has a wide Beta, so it sometimes samples
 * high and gets surfaced — and that boost shrinks automatically as data
 * accrues. The RNG is seeded per request so a session can hold a stable order.
 *
 * Time decay (half-life {@see halfLifeDays}) keeps the ranking seasonal and
 * prevents a stale winner from locking the top.
 */
class ProductRankingService
{
    /**
     * Final storefront order for a day's product rows.
     *
     * Orderable-today rows (`available_on_selected`) are ranked by the bandit;
     * the greyed not-orderable-today remainder keeps its place behind them,
     * seed-shuffled so it also rotates. Returns rows in display order. When the
     * feature flag is off, falls back to the legacy "orderable first" order.
     *
     * @param  Collection<int, array{product: Product, available_on_selected: bool, ...}>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function orderForStorefront(Collection $rows, int $seed): Collection
    {
        [$orderable, $rest] = $rows->partition(fn (array $row): bool => (bool) $row['available_on_selected']);

        if (! (bool) config('shop.ranking.enabled', true)) {
            return $orderable->values()->concat($rest->values())->values();
        }

        $stats = $this->engagementStats();
        $sampler = new BetaSampler($seed);

        $priorAlpha = (float) config('shop.ranking.prior_alpha', 1.0);
        $priorBeta = (float) config('shop.ranking.prior_beta', 20.0);

        // Stable base order before sampling so (seed → order) is reproducible
        // regardless of however the rows arrived.
        $ranked = $orderable
            ->sortBy(fn (array $row): int => (int) $row['product']->id)
            ->map(function (array $row) use ($stats, $sampler, $priorAlpha, $priorBeta): array {
                /** @var Product $product */
                $product = $row['product'];
                $s = $stats->get($product->id);

                $impressions = (float) ($s['impressions'] ?? 0.0);
                $carts = (float) ($s['add_to_carts'] ?? 0.0);
                $purchaseValue = (float) ($s['purchase_value'] ?? 0.0);

                $alpha = $carts + $priorAlpha;
                $beta = max(0.0, $impressions - $carts) + $priorBeta;
                $theta = $sampler->beta($alpha, $beta);

                // Value realized per add-to-cart; cold-start to the product's
                // own per-unit margin/price prior when it has no carts yet.
                $value = $carts > 0.0 ? $purchaseValue / $carts : $product->rankingPriorValue();

                $row['_rank_score'] = $theta * $value;

                return $row;
            })
            ->sortByDesc(fn (array $row): float => $row['_rank_score'])
            ->map(function (array $row): array {
                unset($row['_rank_score']);

                return $row;
            });

        return $ranked->values()
            ->concat($rest->shuffle($seed)->values())
            ->values();
    }

    /**
     * Per-product time-decayed engagement + purchase value, cached.
     *
     * @return Collection<int, array{impressions: float, add_to_carts: float, purchase_value: float}>
     */
    public function engagementStats(): Collection
    {
        $ttl = (int) config('shop.ranking.stats_ttl', 900);

        return Cache::remember('shop.ranking.stats.v1', $ttl, fn (): Collection => $this->computeEngagementStats());
    }

    /**
     * @return Collection<int, array{impressions: float, add_to_carts: float, purchase_value: float}>
     */
    private function computeEngagementStats(): Collection
    {
        $halfLife = max(1, (int) config('shop.ranking.half_life_days', 28));
        $useMargin = config('shop.ranking.objective', 'margin') === 'margin';

        $today = Carbon::today();
        // Beyond ~8 half-lives the weight is < 0.004; bound the scan there.
        $since = $today->copy()->subDays(max(90, $halfLife * 8));

        $stats = [];

        $decay = static fn (int $ageDays): float => 0.5 ** ($ageDays / $halfLife);

        // ── Impressions + add-to-carts from the daily aggregate ──────────────
        ProductEngagementDaily::query()
            ->where('day', '>=', $since->toDateString())
            ->get(['product_id', 'day', 'impressions', 'add_to_carts'])
            ->each(function (ProductEngagementDaily $row) use (&$stats, $decay, $today): void {
                $w = $decay($row->day->diffInDays($today));
                $pid = (int) $row->product_id;
                $stats[$pid]['impressions'] = ($stats[$pid]['impressions'] ?? 0.0) + $row->impressions * $w;
                $stats[$pid]['add_to_carts'] = ($stats[$pid]['add_to_carts'] ?? 0.0) + $row->add_to_carts * $w;
            });

        // ── Realized purchase value (margin or revenue) from order history ───
        ShopOrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->whereNotIn('shop_orders.status', [
                ShopOrderStatus::Cancelled->value,
                ShopOrderStatus::Refunded->value,
            ])
            ->where('shop_orders.created_at', '>=', $since->startOfDay())
            ->with('product')
            ->get([
                'shop_order_items.product_id',
                'shop_order_items.line_total_eur',
                'shop_orders.created_at as order_created_at',
            ])
            ->each(function (ShopOrderItem $item) use (&$stats, $decay, $today, $useMargin): void {
                $pid = (int) $item->product_id;
                $lineTotal = (float) $item->line_total_eur;

                $fraction = $useMargin ? $item->product?->marginFraction() : null;
                // Margin objective: use margin when known, else revenue fallback.
                // Revenue objective: always the full line total.
                $value = ($useMargin && $fraction !== null) ? $lineTotal * $fraction : $lineTotal;

                $age = Carbon::parse($item->getAttribute('order_created_at'))->diffInDays($today);
                $stats[$pid]['purchase_value'] = ($stats[$pid]['purchase_value'] ?? 0.0) + $value * $decay((int) $age);
            });

        return collect($stats)->map(fn (array $s): array => [
            'impressions' => $s['impressions'] ?? 0.0,
            'add_to_carts' => $s['add_to_carts'] ?? 0.0,
            'purchase_value' => $s['purchase_value'] ?? 0.0,
        ]);
    }
}
