<?php

namespace App\Services\Admin;

use App\Enums\RestaurantOrderStatus;
use App\Models\ProducerPositionOverride;
use App\Models\Product;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Manual sourcing moves for the receiving panel. When several farms could
 * cover the same committed position, an admin can move the whole position to
 * another producer — and, for a look-alike product, to another product.
 *
 * The decision persists as a {@see ProducerPositionOverride}; the receiving
 * read-model and the farmer order export honor it while the placed order and
 * its receipt stay untouched (fully reversible).
 *
 * Eligibility is measured against sellable stock LEFT on the delivery date
 * ({@see ProducerAllocationService::remainingByProducer}), reduced by any
 * override already pointing at that producer. The check is conservative: it
 * never offers more than a farm can actually still supply, so a move can never
 * oversell.
 */
class ProducerReassignmentService
{
    /** Names count as similar when they share a common prefix this long. */
    private const PREFIX_MATCH = 5;

    public function __construct(private readonly ProducerAllocationService $allocation) {}

    /**
     * Overrides for the given delivery dates, keyed "pid|vkey|iso".
     *
     * @param  list<string>  $isoDates
     * @return array<string, ProducerPositionOverride>
     */
    public function overridesByPosition(array $isoDates): array
    {
        if ($isoDates === []) {
            return [];
        }

        return ProducerPositionOverride::query()
            ->whereIn('delivery_date', $isoDates)
            ->get()
            ->keyBy(fn (ProducerPositionOverride $o): string => $o->positionKey())
            ->all();
    }

    /**
     * Total committed demand (canonical units) for one position across the
     * receiving scope: finalized shop orders plus committing restaurant lines.
     * This is the quantity a move relocates and must be covered in full.
     */
    public function positionDemand(int $productId, int $variantId, string $iso): float
    {
        $shop = (float) ShopOrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->where('shop_order_items.product_id', $productId)
            ->where(DB::raw('COALESCE(shop_order_items.variant_id, 0)'), $variantId)
            ->whereDate('shop_orders.expected_delivery_date', $iso)
            ->tap(fn ($q) => $this->applyFinalizedShopFilter($q))
            ->sum('shop_order_items.quantity');

        $restaurant = (float) RestaurantOrderItem::query()
            ->join('restaurant_orders', 'restaurant_orders.id', '=', 'restaurant_order_items.restaurant_order_id')
            ->where('restaurant_order_items.product_id', $productId)
            ->where(DB::raw('COALESCE(restaurant_order_items.variant_id, 0)'), $variantId)
            ->whereDate('restaurant_orders.expected_delivery_date', $iso)
            ->whereIn('restaurant_orders.status', RestaurantOrderStatus::reservingValues())
            ->tap(fn ($q) => $this->applyCommittingRestaurantLineFilter($q))
            ->sum('restaurant_order_items.quantity');

        return round($shop + $restaurant, 4);
    }

    /**
     * Sellable-stock capacity per (product, variant, producer) on one date,
     * reduced by overrides already sourcing from that producer. Shape:
     * [pid][vkey][producerId => qty].
     *
     * @param  list<int>  $productIds
     * @return array<int, array<int, array<int, float>>>
     */
    public function capacityFor(array $productIds, string $iso): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $remaining = $this->allocation->remainingByProducer($productIds, [$iso])[$iso] ?? [];

        $capacity = [];
        foreach ($remaining as $pid => $byVkey) {
            foreach ($byVkey as $vkey => $shares) {
                foreach ($shares as $producerId => $qty) {
                    $capacity[(int) $pid][(int) $vkey][(int) $producerId] = (float) $qty;
                }
            }
        }

        // An override that already sources from a producer has claimed that
        // stock even though the raw demand still points at the source product.
        foreach ($this->overridesByPosition([$iso]) as $override) {
            if (! in_array($override->target_product_id, $productIds, true)) {
                continue;
            }
            $qty = $this->positionDemand($override->product_id, $override->variant_id, $iso);
            $pid = $override->target_product_id;
            $vkey = $override->variant_id;
            $producerId = $override->target_producer_id;
            if (isset($capacity[$pid][$vkey][$producerId])) {
                $capacity[$pid][$vkey][$producerId] = max(0.0, $capacity[$pid][$vkey][$producerId] - $qty);
            }
        }

        return $capacity;
    }

    /**
     * Products a position may be moved to: the source itself (a same-product,
     * different-farm move) plus its group siblings and look-alike names. All
     * are candidates; capacity is filtered later per producer.
     *
     * @return array<int, Product> keyed by product id
     */
    public function candidateProducts(Product $source): array
    {
        $with = ['translations', 'producer.translations', 'producers.translations', 'producerVariantPrices'];
        $query = Product::query()->with($with);

        $out = [$source->id => $source];

        // Group siblings share the card; always eligible substitutes.
        if ($source->group_id !== null) {
            $query->clone()->where('group_id', $source->group_id)->get()
                ->each(function (Product $p) use (&$out): void {
                    $out[$p->id] = $p;
                });
        }

        // Look-alike names (zucchini grün ↔ zucchini gelb, kapuzinerblüte ↔
        // kapuzinerblätter). Cheap prefix filter in SQL, refined in PHP.
        $needle = $this->firstToken($source->name);
        if ($needle !== '') {
            Product::query()->with($with)
                ->get()
                ->each(function (Product $p) use (&$out, $source): void {
                    if ($p->id !== $source->id && $this->namesSimilar($source->name, $p->name)) {
                        $out[$p->id] = $p;
                    }
                });
        }

        return $out;
    }

    /**
     * Candidate product ids each source product could be moved to (the source
     * itself, its group siblings and its look-alike names), for many sources at
     * once. Loads the product table a single time so the receiving panel can
     * flag movable rows without a per-row scan.
     *
     * @param  array<int, Product>  $sources  keyed by product id
     * @return array<int, list<int>> keyed by source product id
     */
    public function candidateIdsFor(array $sources): array
    {
        if ($sources === []) {
            return [];
        }

        $all = Product::query()->with('translations')->get();

        $out = [];
        foreach ($sources as $source) {
            $ids = [(int) $source->id => true];
            foreach ($all as $p) {
                if ($source->group_id !== null && $p->group_id === $source->group_id) {
                    $ids[(int) $p->id] = true;
                }
                if ((int) $p->id !== (int) $source->id && $this->namesSimilar((string) $source->name, (string) $p->name)) {
                    $ids[(int) $p->id] = true;
                }
            }
            $out[(int) $source->id] = array_keys($ids);
        }

        return $out;
    }

    /**
     * Eligible move targets for one position. Always lists the source product's
     * OWN registered farms (so a same-product, different-farm move is possible
     * even for a farm with no tracked inflow), plus any other farm or look-alike
     * product that can still cover the whole quantity from stock. The farm it is
     * sourced from now is excluded.
     *
     * @return list<array{target_product_id: int, product_name: string, producer_id: int, producer_name: string, same_product: bool, capacity: float, selling_price: float, selling_price_label: string, buying_price: float|null, buying_price_label: string|null, margin: float|null, margin_label: string|null, price_differs: bool, margin_negative: bool}>
     */
    public function moveTargetsForPosition(
        Product $source,
        int $variantId,
        string $iso,
        float $qty,
        int $currentProductId,
        int $currentProducerId,
    ): array {
        if ($qty <= 1e-9) {
            return [];
        }

        $candidates = $this->candidateProducts($source);
        $capacity = $this->capacityFor(array_keys($candidates), $iso);

        return $this->buildTargets($candidates, $capacity, $source, $variantId, $qty, $currentProductId, $currentProducerId, listSourceFarms: true);
    }

    /**
     * Free-text target search across all products by name, restricted to farms
     * that can cover the whole quantity. Powers the receiving overlay's search.
     * The source product's own farms are covered by {@see moveTargetsForPosition}
     * (the default list), so search stays capacity-gated.
     *
     * @return list<array{target_product_id: int, product_name: string, producer_id: int, producer_name: string, same_product: bool, capacity: float, selling_price: float, selling_price_label: string, buying_price: float|null, buying_price_label: string|null, margin: float|null, margin_label: string|null, price_differs: bool, margin_negative: bool}>
     */
    public function search(
        Product $source,
        string $term,
        int $variantId,
        string $iso,
        float $qty,
        int $currentProductId,
        int $currentProducerId,
    ): array {
        $term = trim($term);
        if ($term === '' || $qty <= 1e-9) {
            return [];
        }

        $needle = mb_strtolower($term);
        $candidates = Product::query()
            ->with(['translations', 'producer.translations', 'producers.translations', 'producerVariantPrices'])
            ->get()
            ->filter(fn (Product $p): bool => str_contains(mb_strtolower((string) $p->name), $needle))
            ->keyBy('id')
            ->all();

        if ($candidates === []) {
            return [];
        }

        $capacity = $this->capacityFor(array_keys($candidates), $iso);

        return $this->buildTargets($candidates, $capacity, $source, $variantId, $qty, $currentProductId, $currentProducerId, listSourceFarms: false);
    }

    /**
     * Persist a move after re-validating eligibility server-side. Throws when
     * the chosen target can no longer cover the position (stock moved on).
     */
    public function move(
        int $sourceProductId,
        int $variantId,
        string $iso,
        int $targetProductId,
        int $targetProducerId,
        ?int $userId,
    ): ProducerPositionOverride {
        $source = Product::query()->findOrFail($sourceProductId);
        $qty = $this->positionDemand($sourceProductId, $variantId, $iso);

        $targets = $this->moveTargetsForPosition(
            $source,
            $variantId,
            $iso,
            $qty,
            // Current sourcing (so the current pairing is not itself a target)
            // is resolved from any existing override, else the source product.
            $this->currentSourcing($sourceProductId, $variantId, $iso)['product_id'],
            $this->currentSourcing($sourceProductId, $variantId, $iso)['producer_id'],
        );

        $ok = collect($targets)->contains(
            fn (array $t): bool => $t['target_product_id'] === $targetProductId && $t['producer_id'] === $targetProducerId,
        );
        abort_unless($ok, 422, 'The chosen producer can no longer cover this position.');

        return DB::transaction(fn (): ProducerPositionOverride => ProducerPositionOverride::query()->updateOrCreate(
            ['product_id' => $sourceProductId, 'variant_id' => $variantId, 'delivery_date' => $iso],
            [
                'target_product_id' => $targetProductId,
                'target_producer_id' => $targetProducerId,
                'created_by_user_id' => $userId,
            ],
        ));
    }

    /** Drop any override on a position so it follows the computed attribution. */
    public function reset(int $sourceProductId, int $variantId, string $iso): void
    {
        ProducerPositionOverride::query()
            ->where('product_id', $sourceProductId)
            ->where('variant_id', $variantId)
            ->where('delivery_date', $iso)
            ->delete();
    }

    /**
     * Where a position is sourced from right now: the override target if set,
     * else the source product with producer 0 (unknown until allocation runs).
     *
     * @return array{product_id: int, producer_id: int}
     */
    public function currentSourcing(int $sourceProductId, int $variantId, string $iso): array
    {
        $override = ProducerPositionOverride::query()
            ->where('product_id', $sourceProductId)
            ->where('variant_id', $variantId)
            ->where('delivery_date', $iso)
            ->first();

        if ($override !== null) {
            return ['product_id' => $override->target_product_id, 'producer_id' => $override->target_producer_id];
        }

        return ['product_id' => $sourceProductId, 'producer_id' => 0];
    }

    /**
     * @param  array<int, Product>  $candidates
     * @param  array<int, array<int, array<int, float>>>  $capacity
     * @return list<array{target_product_id: int, product_name: string, producer_id: int, producer_name: string, same_product: bool, capacity: float, selling_price: float, selling_price_label: string, buying_price: float|null, buying_price_label: string|null, margin: float|null, margin_label: string|null, price_differs: bool, margin_negative: bool}>
     */
    private function buildTargets(
        array $candidates,
        array $capacity,
        Product $source,
        int $variantId,
        float $qty,
        int $currentProductId,
        int $currentProducerId,
        bool $listSourceFarms,
    ): array {
        // The customer keeps the price agreed on the placed order: the source
        // product's selling price is the reference for every target's margin.
        $sellingPrice = round($source->displayUnitPrice(), 2);
        $referenceBuying = $source->buyingPriceForProducer($currentProducerId ?: null, $variantId ?: null);

        $targets = [];
        $seen = [];

        $push = function (Product $product, int $producerId, float $cap) use (
            &$targets, &$seen, $source, $variantId, $currentProductId, $currentProducerId, $sellingPrice, $referenceBuying
        ): void {
            if ($producerId <= 0) {
                return;
            }
            if ($product->id === $currentProductId && $producerId === $currentProducerId) {
                return; // already sourced here
            }
            $dedupe = $product->id.'|'.$producerId;
            if (isset($seen[$dedupe])) {
                return;
            }
            $producerName = $this->producerName($product, $producerId);
            if ($producerName === null) {
                return;
            }
            $seen[$dedupe] = true;

            $sameProduct = $product->id === $source->id;
            $buying = $product->buyingPriceForProducer($producerId, $sameProduct && $variantId > 0 ? $variantId : null);
            $margin = $buying !== null ? round($sellingPrice - $buying, 2) : null;
            $priceDiffers = ($buying !== null && $referenceBuying !== null)
                ? abs($buying - $referenceBuying) > 1e-9
                : $buying !== $referenceBuying;

            $targets[] = [
                'target_product_id' => (int) $product->id,
                'product_name' => (string) $product->name,
                'producer_id' => $producerId,
                'producer_name' => $producerName,
                'same_product' => $sameProduct,
                'capacity' => round($cap, 4),
                'selling_price' => $sellingPrice,
                'selling_price_label' => $this->priceLabel($sellingPrice, $source),
                'buying_price' => $buying,
                'buying_price_label' => $buying !== null ? $this->priceLabel($buying, $product) : null,
                'margin' => $margin,
                'margin_label' => $margin !== null ? Money::formatEur($margin) : null,
                'price_differs' => $priceDiffers,
                'margin_negative' => $margin !== null && $margin < 0,
            ];
        };

        // The source product's own registered farms are always selectable,
        // regardless of tracked inflow: a product listed for a farm can be
        // ordered from that farm even if no stock was recorded yet.
        if ($listSourceFarms) {
            foreach ($this->sourceFarms($source) as $producerId) {
                $cap = $capacity[$source->id][$variantId][$producerId] ?? 0.0;
                $push($source, $producerId, (float) $cap);
            }
        }

        // Stock-backed targets across all candidate products (incl. the source's
        // other farms that do have enough tracked inflow).
        foreach ($candidates as $pid => $product) {
            $shares = $capacity[$pid][$variantId] ?? [];
            foreach ($shares as $producerId => $cap) {
                if ($cap + 1e-9 < $qty) {
                    continue;
                }
                $push($product, (int) $producerId, (float) $cap);
            }
        }

        // Same-product farms first, then by remaining capacity desc, then name.
        usort($targets, fn (array $a, array $b): int => [$b['same_product'] ? 1 : 0, $b['capacity'], $a['product_name']]
            <=> [$a['same_product'] ? 1 : 0, $a['capacity'], $b['product_name']]);

        return $targets;
    }

    /**
     * Producer ids registered for a product: the primary farm plus every farm
     * on the product_producer pivot, de-duplicated.
     *
     * @return list<int>
     */
    private function sourceFarms(Product $source): array
    {
        $ids = [];
        if ($source->producer !== null) {
            $ids[] = (int) $source->producer->id;
        } elseif ($source->producer_id !== null) {
            $ids[] = (int) $source->producer_id;
        }
        foreach ($source->producers as $p) {
            $ids[] = (int) $p->id;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));
    }

    /** "1,50 € / Stück" style label for a per-display-unit price. */
    private function priceLabel(float $eur, Product $product): string
    {
        $money = Money::formatEur($eur);
        $unit = $product->localizedUnit();

        return $unit !== '' ? $money.' / '.$unit : $money;
    }

    /** Name of a producer that can supply this product, or null if unknown. */
    private function producerName(Product $product, int $producerId): ?string
    {
        if ($product->producer !== null && (int) $product->producer->id === $producerId) {
            return (string) $product->producer->name;
        }
        $match = $product->producers->firstWhere('id', $producerId);

        return $match !== null ? (string) $match->name : null;
    }

    private function firstToken(string $name): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', mb_strtolower($name)) ?? '');
        $parts = explode(' ', $normalized);

        return $parts[0] ?? '';
    }

    private function namesSimilar(string $a, string $b): bool
    {
        $a = trim(preg_replace('/\s+/u', ' ', mb_strtolower($a)) ?? '');
        $b = trim(preg_replace('/\s+/u', ' ', mb_strtolower($b)) ?? '');
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        // Share the first word: "zucchini grün" ↔ "zucchini gelb".
        if ($this->firstToken($a) !== '' && $this->firstToken($a) === $this->firstToken($b)) {
            return true;
        }
        // Common prefix long enough: "kapuzinerblüte" ↔ "kapuzinerblätter".
        $len = min(mb_strlen($a), mb_strlen($b));
        $shared = 0;
        for ($i = 0; $i < $len; $i++) {
            if (mb_substr($a, $i, 1) !== mb_substr($b, $i, 1)) {
                break;
            }
            $shared++;
        }

        return $shared >= self::PREFIX_MATCH;
    }

    /** Finalized shop orders: Paid, Fulfilled, or in-person PendingPayment. */
    private function applyFinalizedShopFilter($query): void
    {
        // Mirror ShopOrder::scopeFinalized so the demand matches the panel.
        $query->whereIn('shop_orders.id', ShopOrder::query()->finalized()->select('shop_orders.id'));
    }

    /**
     * Restaurant lines that actually hold stock, mirroring
     * {@see InventoryService} so the moved quantity equals
     * the inventory commitment: hard-reserved, or a confirmed line of a firm
     * order. Declined lines never count.
     */
    private function applyCommittingRestaurantLineFilter($query): void
    {
        $query->where(function ($q): void {
            $q->where('restaurant_order_items.track', 'reserved')
                ->orWhere(function ($q): void {
                    $q->where('restaurant_order_items.line_status', 'confirmed')
                        ->where('restaurant_orders.kind', 'order');
                });
        })
            ->where('restaurant_order_items.line_status', '!=', 'declined');
    }
}
