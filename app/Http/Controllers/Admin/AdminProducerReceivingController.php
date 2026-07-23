<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerType;
use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Models\ProducerPositionOverride;
use App\Models\ProducerReceipt;
use App\Models\Product;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Services\Admin\ProducerAllocationService;
use App\Services\Admin\ProducerReassignmentService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orders grouped by the farm that supplies them, for three jobs:
 *  1. Hand a farmer their order (reuses the shop-orders farmer export ZIP).
 *  2. Tick off produce as it arrives (the receiving checklist, backed by the
 *     producer_receipts table — operational only, never touches inventory).
 *  3. Move a whole position to another farm (or a look-alike product) when
 *     several farms could cover it — persisted as a producer_position_override
 *     that the panel and the farmer export honor without rewriting the order.
 *
 * Demand spans the finalized shop orders AND the committing restaurant lines
 * for the delivery window, so both channels are movable.
 */
class AdminProducerReceivingController extends Controller
{
    /** Pseudo-status: the firm order set (Paid, Fulfilled, in-person pending). */
    private const FILTER_FINALIZED = 'finalized';

    /** Below this order value (EUR) a farm is barely worth a separate pickup. */
    private const CONSOLIDATE_VALUE_THRESHOLD_EUR = 20.0;

    public function __construct(private readonly ProducerReassignmentService $reassignment) {}

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $filter = $this->resolveFilter($request);
        $producers = $this->buildProducers($filter);

        return view('admin.producer-receiving.index', [
            'producers' => $producers,
            'filter' => $filter,
        ]);
    }

    /**
     * Tick a single line on or off. Row present in producer_receipts means
     * received; toggling removes it. Idempotent per call.
     */
    public function toggleReceipt(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'producer_id' => ['required', 'integer', 'exists:producers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'delivery_date' => ['required', 'date'],
        ]);

        $key = [
            'producer_id' => (int) $data['producer_id'],
            'product_id' => (int) $data['product_id'],
            'variant_id' => (int) ($data['variant_id'] ?? 0),
            'delivery_date' => Carbon::parse($data['delivery_date'])->toDateString(),
        ];

        $existing = ProducerReceipt::query()->where($key)->first();
        if ($existing !== null) {
            $existing->delete();

            return response()->json(['received' => false]);
        }

        ProducerReceipt::create($key + ['received_by_user_id' => $request->user()?->id]);

        return response()->json(['received' => true]);
    }

    /**
     * Mark every line of one producer (for the current filter) as received.
     * Recomputes the producer's row set server-side so the frontend cannot
     * drive us past what the orders actually contain.
     */
    public function receiveAll(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $producerId = (int) $request->input('producer_id', 0);
        abort_unless($producerId > 0, 422, 'producer_id is required');

        $filter = $this->resolveFilter($request);
        $producers = $this->buildProducers($filter);
        $producer = collect($producers)->firstWhere('id', $producerId);
        abort_if($producer === null, 404);

        $keys = [];
        foreach ($producer['rows'] as $row) {
            ProducerReceipt::firstOrCreate([
                'producer_id' => $producerId,
                'product_id' => $row['product_id'],
                'variant_id' => $row['variant_id'],
                'delivery_date' => $row['date'],
            ], ['received_by_user_id' => $request->user()?->id]);
            $keys[] = $row['key'];
        }

        return response()->json(['keys' => $keys]);
    }

    /**
     * Farms (and look-alike products) a position can be moved to. Recomputed
     * server-side; the client never decides eligibility.
     */
    public function moveTargets(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'delivery_date' => ['required', 'date'],
            'current_producer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $source = Product::query()
            ->with(['translations', 'producer.translations', 'producers.translations', 'producerVariantPrices'])
            ->findOrFail($data['product_id']);
        $variantId = (int) ($data['variant_id'] ?? 0);
        $iso = Carbon::parse($data['delivery_date'])->toDateString();
        $qty = $this->reassignment->positionDemand($source->id, $variantId, $iso);
        $current = $this->reassignment->currentSourcing($source->id, $variantId, $iso);
        // The row already sits under a farm; exclude that exact farm and price
        // the move against what it costs there today.
        $currentProducerId = (int) ($data['current_producer_id'] ?? $current['producer_id']);

        $search = trim((string) ($data['search'] ?? ''));
        $targets = $search !== ''
            ? $this->reassignment->search($source, $search, $variantId, $iso, $qty, $current['product_id'], $currentProducerId)
            : $this->reassignment->moveTargetsForPosition($source, $variantId, $iso, $qty, $current['product_id'], $currentProducerId);

        return response()->json(['targets' => $targets]);
    }

    /** Persist a move; the target must still cover the whole position. */
    public function move(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'delivery_date' => ['required', 'date'],
            'target_product_id' => ['required', 'integer', 'exists:products,id'],
            'target_producer_id' => ['required', 'integer', 'exists:producers,id'],
        ]);

        $this->reassignment->move(
            (int) $data['product_id'],
            (int) ($data['variant_id'] ?? 0),
            Carbon::parse($data['delivery_date'])->toDateString(),
            (int) $data['target_product_id'],
            (int) $data['target_producer_id'],
            $request->user()?->id,
        );

        return response()->json(['ok' => true]);
    }

    /** Drop a move so the position follows the computed attribution again. */
    public function resetMove(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'delivery_date' => ['required', 'date'],
        ]);

        $this->reassignment->reset(
            (int) $data['product_id'],
            (int) ($data['variant_id'] ?? 0),
            Carbon::parse($data['delivery_date'])->toDateString(),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * One entry per producer that supplies any of the filtered demand, each
     * holding the (date, product, variant) lines it owes with received flags,
     * a movable flag and — where a move is in effect — its override details.
     *
     * @param  array{status: string, type: string, delivery_from: string, delivery_to: string}  $filter
     * @return list<array<string, mixed>>
     */
    private function buildProducers(array $filter): array
    {
        $demand = $this->demand($filter);
        if ($demand === []) {
            return [];
        }

        [$productModels, $meta] = $this->productData($demand);
        $isos = $this->isosOf($demand);
        $received = $this->receivedKeys($isos);

        $allocation = app(ProducerAllocationService::class)
            ->allocate(array_keys($productModels), $isos, $demand);

        $overrides = $this->reassignment->overridesByPosition($isos);

        // Products each position could move to (same product, group siblings,
        // look-alike names), so the movable flag also lights up when only a
        // look-alike product from another farm can cover the position.
        $candIdsByProduct = $this->reassignment->candidateIdsFor($productModels);

        // Capacity per date over every candidate product (not just the demanded
        // ones), with existing overrides already subtracted.
        $capacityIds = array_keys($productModels);
        foreach ($candIdsByProduct as $ids) {
            $capacityIds = array_merge($capacityIds, $ids);
        }
        $capacityIds = array_values(array_unique($capacityIds));
        $capacityByIso = [];
        foreach ($isos as $iso) {
            $capacityByIso[$iso] = $this->reassignment->capacityFor($capacityIds, $iso);
        }

        // Ensure override targets have a product model + meta available.
        $productModels = $this->withOverrideTargets($productModels, $overrides);

        $byProducer = [];

        // 1. Default attribution for positions WITHOUT an override.
        foreach ($allocation as $iso => $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    $variantId = (int) ($vkey ?: 0);
                    if (isset($overrides[$pid.'|'.$variantId.'|'.$iso])) {
                        continue; // moved: handled below
                    }
                    $m = $meta[$pid.'|'.$variantId] ?? null;
                    if ($m === null) {
                        continue;
                    }
                    $positionQty = (float) ($demand[$pid][$variantId][$iso] ?? array_sum($shares));
                    $product = $productModels[$pid] ?? null;
                    foreach ($shares as $producerId => $qty) {
                        if ($producerId <= 0 || $qty <= 1e-9) {
                            continue; // 0 = unattributable demand
                        }
                        $byProducer[$producerId][] = $this->row(
                            $producerId, (int) $pid, $variantId, $iso, $m,
                            round($m['factor'] > 0 ? $qty / $m['factor'] : $qty, 4),
                            $received,
                            // Movable when another farm is registered for the same
                            // product (even without tracked inflow), or when another
                            // farm still has enough stock to cover the position from
                            // the same product OR a look-alike / sibling product.
                            movable: $this->hasOtherRegisteredFarm($product, $producerId)
                                || $this->hasCapableTarget($candIdsByProduct, $capacityByIso, $iso, (int) $pid, $variantId, $positionQty, $producerId),
                        );
                    }
                }
            }
        }

        // 2. Overridden positions: one row under the chosen target producer.
        foreach ($overrides as $override) {
            $iso = $override->delivery_date;
            if (! in_array($iso, $isos, true)) {
                continue;
            }
            $sourcePid = $override->product_id;
            $variantId = $override->variant_id;
            $sourceMeta = $meta[$sourcePid.'|'.$variantId] ?? null;
            if ($sourceMeta === null) {
                continue; // source no longer in the filtered demand
            }
            $qty = $this->reassignment->positionDemand($sourcePid, $variantId, $iso);
            if ($qty <= 1e-9) {
                continue;
            }
            $targetProduct = $productModels[$override->target_product_id] ?? null;
            $targetProducer = Producer::query()->find($override->target_producer_id);
            if ($targetProduct === null || $targetProducer === null) {
                continue;
            }
            $receiptVariant = $override->target_product_id === $sourcePid ? $variantId : 0;
            $row = $this->row(
                $override->target_producer_id,
                $override->target_product_id,
                $receiptVariant,
                $iso,
                [
                    'product_name' => $targetProduct->name,
                    'variant_name' => $receiptVariant !== 0 ? $sourceMeta['variant_name'] : null,
                    'unit' => $targetProduct->localizedUnit(),
                    'factor' => $sourceMeta['factor'],
                ],
                round($sourceMeta['factor'] > 0 ? $qty / $sourceMeta['factor'] : $qty, 4),
                $received,
                movable: true,
            );
            $sourceProduct = $productModels[$sourcePid] ?? null;
            $row['overridden'] = true;
            $row['override'] = [
                'source_product_id' => $sourcePid,
                'source_variant_id' => $variantId,
                'source_product_name' => $sourceMeta['product_name'],
                'target_product_id' => $override->target_product_id,
                'target_product_name' => $targetProduct->name,
                'target_producer_id' => $override->target_producer_id,
                'target_producer_name' => $targetProducer->name,
            ] + $this->priceWarning($sourceProduct, $targetProduct, $override->target_producer_id, $variantId, $receiptVariant);

            // If the target farm already delivers this exact product+variant on
            // this day, consolidate onto that native line instead of emitting a
            // second row with the same key (which would collide in the panel).
            $existingIdx = null;
            foreach ($byProducer[$override->target_producer_id] ?? [] as $i => $existing) {
                if ($existing['key'] === $row['key']) {
                    $existingIdx = $i;
                    break;
                }
            }
            if ($existingIdx !== null) {
                $byProducer[$override->target_producer_id][$existingIdx]['qty_display'] =
                    round($byProducer[$override->target_producer_id][$existingIdx]['qty_display'] + $row['qty_display'], 4);
                $byProducer[$override->target_producer_id][$existingIdx]['overridden'] = true;
                $byProducer[$override->target_producer_id][$existingIdx]['override'] = $row['override'];
            } else {
                $byProducer[$override->target_producer_id][] = $row;
            }
        }

        $this->annotateRecommendations($byProducer, $candIdsByProduct, $capacityByIso, $demand, $productModels);

        return $this->assembleProducers($byProducer);
    }

    /**
     * One receiving row. `move` keys default to "not moved"; the override loop
     * fills them for relocated positions.
     *
     * @param  array<string, mixed>  $m  display metadata (name, variant, unit, factor)
     * @param  array<string, true>  $received
     * @return array<string, mixed>
     */
    private function row(int $producerId, int $productId, int $variantId, string $iso, array $m, float $qtyDisplay, array $received, bool $movable): array
    {
        return [
            'key' => "p{$producerId}-{$productId}-{$variantId}-{$iso}",
            'product_id' => $productId,
            'variant_id' => $variantId,
            'date' => $iso,
            'product_name' => $m['product_name'],
            'variant_name' => $m['variant_name'] ?? null,
            'qty_display' => $qtyDisplay,
            'unit' => $m['unit'],
            'received' => isset($received[$producerId.'|'.$productId.'|'.$variantId.'|'.$iso]),
            'movable' => $movable,
            'recommended' => false,
            'recommend_reason' => null,
            'recommend_target_producers' => [],
            'overridden' => false,
            'override' => null,
        ];
    }

    /**
     * Margin warning for a moved position. The customer keeps the price agreed
     * on the source order, so we compare that selling price against what the
     * TARGET farm charges. When the buying price differs (and especially when
     * the margin turns negative) the admin should see it on the moved row.
     *
     * @return array{selling_price_label: string|null, buying_price_label: string|null, margin_label: string|null, price_differs: bool, margin_negative: bool}
     */
    private function priceWarning(?Product $source, Product $target, int $targetProducerId, int $sourceVariantId, int $receiptVariant): array
    {
        $blank = [
            'selling_price_label' => null,
            'buying_price_label' => null,
            'margin_label' => null,
            'price_differs' => false,
            'margin_negative' => false,
        ];
        if ($source === null) {
            return $blank;
        }

        $selling = round($source->displayUnitPrice(), 2);
        $buying = $target->buyingPriceForProducer($targetProducerId, $receiptVariant !== 0 ? $receiptVariant : null);
        // Reference: what the source position would cost unmoved (its default).
        $reference = $source->buyingPriceForProducer(null, $sourceVariantId !== 0 ? $sourceVariantId : null);
        $margin = $buying !== null ? round($selling - $buying, 2) : null;

        return [
            'selling_price_label' => Money::formatEur($selling),
            'buying_price_label' => $buying !== null ? Money::formatEur($buying) : null,
            'margin_label' => $margin !== null ? Money::formatEur($margin) : null,
            'price_differs' => ($buying !== null && $reference !== null)
                ? abs($buying - $reference) > 1e-9
                : $buying !== $reference,
            'margin_negative' => $margin !== null && $margin < 0,
        ];
    }

    /**
     * Does the product list a farm other than the current one? A farm on the
     * product_producer pivot (or the primary farm) is a valid same-product move
     * target regardless of tracked inflow.
     */
    private function hasOtherRegisteredFarm(?Product $product, int $currentProducerId): bool
    {
        if ($product === null) {
            return false;
        }
        $ids = [];
        if ($product->producer_id !== null) {
            $ids[(int) $product->producer_id] = true;
        }
        foreach ($product->producers as $p) {
            $ids[(int) $p->id] = true;
        }
        unset($ids[$currentProducerId]);

        return $ids !== [];
    }

    /**
     * Could the whole position move to a farm with enough stock on this date,
     * across the same product OR any of its look-alike / sibling products? The
     * current pairing (same product, same farm) never counts as a target.
     *
     * @param  array<int, list<int>>  $candIdsByProduct
     * @param  array<string, array<int, array<int, array<int, float>>>>  $capacityByIso
     */
    private function hasCapableTarget(array $candIdsByProduct, array $capacityByIso, string $iso, int $pid, int $variantId, float $qty, int $currentProducerId): bool
    {
        return $this->capableTargetFarms($candIdsByProduct, $capacityByIso, $iso, $pid, $variantId, $qty, $currentProducerId) !== [];
    }

    /**
     * Farms (other than the current one) that already take a fitting product
     * on this day AND still have the stock to cover this whole position. These
     * are the genuine consolidation targets: moving here merges onto an order
     * the farm already has, rather than opening a brand-new position.
     *
     * @param  array<string, list<array{pid: int, producer: int}>>  $activeByIso
     * @param  array<int, list<int>>  $candIdsByProduct
     * @param  array<string, array<int, array<int, array<int, float>>>>  $capacityByIso
     * @return list<int>
     */
    private function analogHolderFarms(string $iso, int $pid, int $variantId, int $currentProducerId, array $activeByIso, array $candIdsByProduct, array $capacityByIso, float $qty): array
    {
        $analog = array_flip($candIdsByProduct[$pid] ?? [$pid]); // includes the product itself

        $holders = [];
        foreach ($activeByIso[$iso] ?? [] as $entry) {
            if ($entry['producer'] === $currentProducerId) {
                continue;
            }
            if (isset($analog[$entry['pid']])) {
                $holders[$entry['producer']] = true;
            }
        }

        $capable = array_flip($this->capableTargetFarms($candIdsByProduct, $capacityByIso, $iso, $pid, $variantId, $qty, $currentProducerId));

        return array_values(array_filter(array_keys($holders), fn (int $id): bool => isset($capable[$id])));
    }

    /**
     * Every farm that could cover this whole position from stock on this day,
     * across the same product OR any look-alike / sibling. Excludes the farm the
     * position is currently sourced from: a move only makes sense to another farm.
     *
     * @param  array<int, list<int>>  $candIdsByProduct
     * @param  array<string, array<int, array<int, array<int, float>>>>  $capacityByIso
     * @return list<int>
     */
    private function capableTargetFarms(array $candIdsByProduct, array $capacityByIso, string $iso, int $pid, int $variantId, float $qty, int $currentProducerId): array
    {
        if ($qty <= 1e-9) {
            return [];
        }
        $farms = [];
        foreach ($candIdsByProduct[$pid] ?? [$pid] as $candidateId) {
            foreach ($capacityByIso[$iso][$candidateId][$variantId] ?? [] as $producerId => $cap) {
                if ($producerId <= 0) {
                    continue;
                }
                if ($producerId === $currentProducerId) {
                    continue; // a move only makes sense to a different farm
                }
                if ($cap + 1e-9 >= $qty) {
                    $farms[(int) $producerId] = true;
                }
            }
        }

        return array_keys($farms);
    }

    /**
     * Flag rows where changing farm is genuinely recommended, so the panel can
     * paint the button (and only the right destinations) red. Two triggers:
     *  - "consolidate": another farm already takes a fitting product it could
     *    absorb this into. Only those farms are marked, never a farm that would
     *    open a brand-new position.
     *  - "low_value": a farm whose whole order here is worth less than the
     *    threshold. Only the movable lines that can actually be covered elsewhere
     *    are flagged (each pointing at the farms that can take it), never a whole
     *    farm just because one of its lines fits.
     *
     * @param  array<int, list<array<string, mixed>>>  $byProducer
     * @param  array<int, list<int>>  $candIdsByProduct
     * @param  array<string, array<int, array<int, array<int, float>>>>  $capacityByIso
     * @param  array<int, array<int, array<string, float>>>  $demand
     * @param  array<int, Product>  $productModels
     */
    private function annotateRecommendations(array &$byProducer, array $candIdsByProduct, array $capacityByIso, array $demand, array $productModels): void
    {
        // Active (not-yet-moved) lines per day: which farm already takes which product.
        $activeByIso = [];
        foreach ($byProducer as $producerId => $rows) {
            foreach ($rows as $r) {
                if ($r['overridden']) {
                    continue;
                }
                $activeByIso[$r['date']][] = ['pid' => (int) $r['product_id'], 'producer' => (int) $producerId];
            }
        }

        foreach ($byProducer as $producerId => &$rows) {
            $producerId = (int) $producerId;
            $farmIsTiny = $this->farmValueBelowThreshold($rows, $productModels);

            foreach ($rows as &$row) {
                if ($row['overridden'] || $row['received'] || ! $row['movable']) {
                    continue;
                }
                $pid = (int) $row['product_id'];
                $variantId = (int) $row['variant_id'];
                $iso = (string) $row['date'];
                $qty = (float) ($demand[$pid][$variantId][$iso] ?? 0.0);
                if ($qty <= 1e-9) {
                    continue;
                }

                $holders = $this->analogHolderFarms($iso, $pid, $variantId, $producerId, $activeByIso, $candIdsByProduct, $capacityByIso, $qty);
                if ($holders !== []) {
                    $row['recommended'] = true;
                    $row['recommend_reason'] = 'consolidate';
                    $row['recommend_target_producers'] = $holders;
                } elseif ($farmIsTiny) {
                    $capable = $this->capableTargetFarms($candIdsByProduct, $capacityByIso, $iso, $pid, $variantId, $qty, $producerId);
                    if ($capable !== []) {
                        $row['recommended'] = true;
                        $row['recommend_reason'] = 'low_value';
                        $row['recommend_target_producers'] = $capable;
                    }
                }
            }
            unset($row);
        }
        unset($rows);
    }

    /**
     * Is this farm's whole order here worth less than the consolidation
     * threshold? Such a farm is barely worth a separate pickup, so each of its
     * movable lines that has a covering farm is advised to move. The per-line
     * capability check happens in {@see annotateRecommendations}, so a low-value
     * farm only flags the specific products that can actually move, never all of
     * them just because one fits.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, Product>  $productModels
     */
    private function farmValueBelowThreshold(array $rows, array $productModels): bool
    {
        $value = 0.0;
        foreach ($rows as $r) {
            $product = $productModels[(int) $r['product_id']] ?? null;
            if ($product !== null) {
                $value += (float) $r['qty_display'] * (float) $product->displayUnitPrice();
            }
        }

        return $value < self::CONSOLIDATE_VALUE_THRESHOLD_EUR;
    }

    /**
     * Combined demand [pid][vkey][iso => qty] across the finalized shop orders
     * and the committing restaurant lines for the window. A b2c type filter
     * drops restaurant demand (restaurant is B2B); b2b drops private shop rows.
     *
     * @param  array{status: string, type: string, delivery_from: string, delivery_to: string}  $filter
     * @return array<int, array<int, array<string, float>>>
     */
    private function demand(array $filter): array
    {
        $demand = [];

        $this->filteredOrdersQuery($filter)
            ->with(['items:id,shop_order_id,product_id,variant_id,quantity'])
            ->get()
            ->each(function (ShopOrder $order) use (&$demand): void {
                $iso = $order->expected_delivery_date?->toDateString();
                if ($iso === null) {
                    return;
                }
                foreach ($order->items as $item) {
                    if ($item->product_id === null) {
                        continue; // custom line, no farm
                    }
                    $pid = (int) $item->product_id;
                    $vkey = (int) ($item->variant_id ?? 0);
                    $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + (float) $item->quantity;
                }
            });

        if ($filter['type'] !== 'b2c') {
            foreach ($this->restaurantDemand($filter) as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $byIso) {
                    foreach ($byIso as $iso => $qty) {
                        $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + $qty;
                    }
                }
            }
        }

        return $demand;
    }

    /**
     * Committing restaurant demand for the window, grouped like shop demand.
     *
     * @param  array{status: string, type: string, delivery_from: string, delivery_to: string}  $filter
     * @return array<int, array<int, array<string, float>>>
     */
    private function restaurantDemand(array $filter): array
    {
        $query = $this->committingRestaurantItemsQuery();

        if ($filter['delivery_from'] !== '') {
            $query->whereDate('restaurant_orders.expected_delivery_date', '>=', $filter['delivery_from']);
        }
        if ($filter['delivery_to'] !== '') {
            $query->whereDate('restaurant_orders.expected_delivery_date', '<=', $filter['delivery_to']);
        }

        $out = [];
        $query->groupBy('restaurant_order_items.product_id', 'restaurant_order_items.variant_id', 'restaurant_orders.expected_delivery_date')
            ->get([
                'restaurant_order_items.product_id as product_id',
                'restaurant_order_items.variant_id as variant_id',
                'restaurant_orders.expected_delivery_date as iso',
                DB::raw('SUM(restaurant_order_items.quantity) as qty'),
            ])
            ->each(function ($row) use (&$out): void {
                $pid = (int) $row->product_id;
                $vkey = (int) ($row->variant_id ?? 0);
                $iso = Carbon::parse($row->iso)->toDateString();
                $out[$pid][$vkey][$iso] = ($out[$pid][$vkey][$iso] ?? 0.0) + (float) $row->qty;
            });

        return $out;
    }

    /**
     * Product models and display metadata for the demanded positions.
     *
     * @param  array<int, array<int, array<string, float>>>  $demand
     * @return array{0: array<int, Product>, 1: array<string, array<string, mixed>>}
     */
    private function productData(array $demand): array
    {
        $models = Product::query()
            ->whereIn('id', array_keys($demand))
            ->with(['translations', 'variants', 'producer.translations', 'producers.translations'])
            ->get()
            ->keyBy('id')
            ->all();

        $meta = [];
        foreach ($demand as $pid => $byVkey) {
            $product = $models[$pid] ?? null;
            if ($product === null) {
                continue;
            }
            $unit = $product->unit;
            $displayUnit = $product->price_display_unit ?? $unit?->defaultDisplayUnit();
            $factor = ($unit !== null && $displayUnit !== null)
                ? $unit->canonicalUnitsPerDisplay($displayUnit)
                : 1;
            foreach (array_keys($byVkey) as $vkey) {
                $variantId = (int) $vkey;
                $variant = $variantId !== 0 ? $product->variants->firstWhere('id', $variantId) : null;
                $meta[$pid.'|'.$variantId] = [
                    'product_name' => $product->name,
                    'variant_name' => $variant?->localizedName(),
                    'factor' => $factor,
                    'unit' => $product->localizedUnit(),
                ];
            }
        }

        return [$models, $meta];
    }

    /**
     * Add product models for override targets not already loaded (a look-alike
     * substitution points at a product outside the demand set).
     *
     * @param  array<int, Product>  $models
     * @param  array<string, ProducerPositionOverride>  $overrides
     * @return array<int, Product>
     */
    private function withOverrideTargets(array $models, array $overrides): array
    {
        $missing = [];
        foreach ($overrides as $override) {
            if (! isset($models[$override->target_product_id])) {
                $missing[$override->target_product_id] = true;
            }
        }
        if ($missing === []) {
            return $models;
        }

        Product::query()
            ->whereIn('id', array_keys($missing))
            ->with(['translations', 'variants', 'producer.translations', 'producers.translations'])
            ->get()
            ->each(function (Product $p) use (&$models): void {
                $models[$p->id] = $p;
            });

        return $models;
    }

    /**
     * Sort rows per producer and wrap them with counts.
     *
     * @param  array<int, list<array<string, mixed>>>  $byProducer
     * @return list<array<string, mixed>>
     */
    private function assembleProducers(array $byProducer): array
    {
        $names = Producer::query()
            ->whereIn('id', array_keys($byProducer))
            ->with('translations')
            ->get()
            ->mapWithKeys(fn (Producer $p): array => [$p->id => $p->name]);

        $producers = [];
        foreach ($byProducer as $producerId => $rows) {
            usort($rows, fn (array $a, array $b): int => [$a['date'], $a['product_name'], (string) ($a['variant_name'] ?? '')]
                <=> [$b['date'], $b['product_name'], (string) ($b['variant_name'] ?? '')]);

            $receivedCount = count(array_filter($rows, fn (array $r): bool => $r['received']));
            $producers[] = [
                'id' => (int) $producerId,
                'name' => $names[$producerId] ?? ('#'.$producerId),
                'rows' => $rows,
                'total_count' => count($rows),
                'received_count' => $receivedCount,
                'done' => $receivedCount === count($rows) && $rows !== [],
            ];
        }

        usort($producers, fn (array $a, array $b): int => [$a['done'] ? 1 : 0, mb_strtolower($a['name'])]
            <=> [$b['done'] ? 1 : 0, mb_strtolower($b['name'])]);

        return $producers;
    }

    /**
     * Distinct delivery dates present in the demand map.
     *
     * @param  array<int, array<int, array<string, float>>>  $demand
     * @return list<string>
     */
    private function isosOf(array $demand): array
    {
        $isos = [];
        foreach ($demand as $byVkey) {
            foreach ($byVkey as $byIso) {
                foreach (array_keys($byIso) as $iso) {
                    $isos[$iso] = true;
                }
            }
        }

        return array_keys($isos);
    }

    /**
     * Set of already-received keys "producer|product|variant|iso" for the
     * delivery window, so lookups are O(1) while building rows.
     *
     * @param  list<string>  $isos
     * @return array<string, true>
     */
    private function receivedKeys(array $isos): array
    {
        if ($isos === []) {
            return [];
        }

        $keys = [];
        ProducerReceipt::query()
            ->whereIn('delivery_date', $isos)
            ->get()
            ->each(function (ProducerReceipt $r) use (&$keys): void {
                $keys[$r->producer_id.'|'.$r->product_id.'|'.$r->variant_id.'|'.$r->delivery_date] = true;
            });

        return $keys;
    }

    /**
     * @return array{status: string, type: string, delivery_from: string, delivery_to: string}
     */
    private function resolveFilter(Request $request): array
    {
        $from = (string) $request->query('delivery_from', '');
        $to = (string) $request->query('delivery_to', '');

        // Fresh visit (no date params at all): focus on the next delivery day
        // that has orders, or the most recent past one if nothing is upcoming,
        // so the panel is never an open-ended range. Explicitly cleared inputs
        // still mean "all dates".
        if (! $request->has('delivery_from') && ! $request->has('delivery_to')) {
            $default = $this->defaultDeliveryDay();
            if ($default !== null) {
                $from = $to = $default;
            }
        }

        $type = (string) $request->query('type', '');
        if (! in_array($type, ['', 'b2c', 'b2b'], true)) {
            $type = '';
        }

        return [
            'status' => self::FILTER_FINALIZED,
            'type' => $type,
            'delivery_from' => $from,
            'delivery_to' => $to,
        ];
    }

    /**
     * Firm shop orders in the delivery-date window. Matches the farmer export
     * (shop orders only) so the checklist and the export ZIP agree.
     *
     * @param  array{status: string, type: string, delivery_from: string, delivery_to: string}  $filter
     * @return Builder<ShopOrder>
     */
    private function filteredOrdersQuery(array $filter): Builder
    {
        return ShopOrder::query()
            ->finalized()
            ->when($filter['type'], fn (Builder $q, string $type): Builder => match ($type) {
                'b2c' => $q->where('customer_type', CustomerType::Private->value),
                'b2b' => $q->where('customer_type', CustomerType::Business->value),
                default => $q,
            })
            ->when($filter['delivery_from'] !== '', fn (Builder $q): Builder => $q->whereDate('expected_delivery_date', '>=', $filter['delivery_from']))
            ->when($filter['delivery_to'] !== '', fn (Builder $q): Builder => $q->whereDate('expected_delivery_date', '<=', $filter['delivery_to']));
    }

    /**
     * The delivery day a fresh visit should land on: the next one (today or
     * later) with orders, or the most recent past one if nothing is upcoming.
     * Considers both firm shop orders and committing restaurant lines, matching
     * the demand the panel actually renders.
     */
    private function defaultDeliveryDay(): ?string
    {
        $today = Carbon::today()->toDateString();

        return $this->nextDeliveryDayWithOrders($today)
            ?? $this->lastDeliveryDayWithOrders();
    }

    /** Earliest delivery date on or after $today that has any demand. */
    private function nextDeliveryDayWithOrders(string $today): ?string
    {
        $shop = ShopOrder::query()
            ->finalized()
            ->whereDate('expected_delivery_date', '>=', $today)
            ->min('expected_delivery_date');

        $restaurant = $this->committingRestaurantItemsQuery()
            ->whereDate('restaurant_orders.expected_delivery_date', '>=', $today)
            ->min('restaurant_orders.expected_delivery_date');

        return $this->pickDate($shop, $restaurant, earliest: true);
    }

    /** Most recent delivery date that has any demand (used when none upcoming). */
    private function lastDeliveryDayWithOrders(): ?string
    {
        $shop = ShopOrder::query()
            ->finalized()
            ->max('expected_delivery_date');

        $restaurant = $this->committingRestaurantItemsQuery()
            ->max('restaurant_orders.expected_delivery_date');

        return $this->pickDate($shop, $restaurant, earliest: false);
    }

    /**
     * The committing restaurant order lines (reserved, or confirmed order
     * lines), joined to their orders. Shared by the demand build and the
     * default-date resolution so both agree on what counts as an order.
     *
     * @return Builder<RestaurantOrderItem>
     */
    private function committingRestaurantItemsQuery(): Builder
    {
        return RestaurantOrderItem::query()
            ->join('restaurant_orders', 'restaurant_orders.id', '=', 'restaurant_order_items.restaurant_order_id')
            ->whereIn('restaurant_orders.status', RestaurantOrderStatus::reservingValues())
            ->where(function ($q): void {
                $q->where('restaurant_order_items.track', 'reserved')
                    ->orWhere(function ($q): void {
                        $q->where('restaurant_order_items.line_status', 'confirmed')
                            ->where('restaurant_orders.kind', 'order');
                    });
            })
            ->where('restaurant_order_items.line_status', '!=', 'declined')
            ->whereNotNull('restaurant_order_items.product_id');
    }

    /** Pick the earliest or latest of two nullable dates, normalized to Y-m-d. */
    private function pickDate(?string $a, ?string $b, bool $earliest): ?string
    {
        $dates = array_values(array_filter([
            $a !== null ? Carbon::parse($a)->toDateString() : null,
            $b !== null ? Carbon::parse($b)->toDateString() : null,
        ]));
        if ($dates === []) {
            return null;
        }

        return $earliest ? min($dates) : max($dates);
    }
}
