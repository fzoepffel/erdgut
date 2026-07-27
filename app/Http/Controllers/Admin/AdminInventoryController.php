<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShopStockAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInventoryInflowStoreRequest;
use App\Http\Requests\Admin\AdminInventoryInflowUpdateRequest;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\InventoryService;
use App\Services\Admin\ProducerAllocationService;
use App\Support\InventoryProductClusters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Daily-inventory admin page — the post-Phase-2 replacement for the
 * "Wochenkatalog".
 *
 * `index()` renders a 14-day window starting from a chosen date.
 * `storeInflow()` records one ad-hoc receipt; `destroyInflow()`
 * removes one. Recurring schedules live on a sibling controller
 * (`AdminInflowScheduleController`) so the routes stay tidy.
 */
class AdminInventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProducerAllocationService $allocation,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $from = $request->query('from');
        $start = ($from !== null && $from !== '')
            ? Carbon::parse((string) $from)->startOfDay()
            : Carbon::today();

        // 14-day window, inclusive on both ends.
        $end = $start->copy()->addDays(13);

        // Optional page-level filters — applied to BOTH the availability
        // matrix and the inflow table so the two views stay in sync.
        //   product   case-insensitive substring match on the localized
        //             product name (via product_translations)
        //   producer  match products whose supplier list includes this
        //             producer (the pivot carries the primary, so the
        //             products.producer_id case is included). The inflow
        //             query also matches when the delivering farm on the
        //             batch itself is the filter, catching batches from
        //             a non-registered supplier.
        $productFilter = trim((string) $request->query('product', ''));
        $producerFilter = (int) $request->query('producer', 0);
        $searchingByName = $productFilter !== '';

        // Default: only products whose stock can affect this 14-day window
        // (inflow received in/near the window). Name search unlocks the full
        // published catalog so admins can find empty products and add inflows.
        $candidates = Product::query()
            ->with(['translations'])
            ->when(
                $searchingByName,
                function ($q) use ($start, $end): void {
                    $q->where(function ($outer) use ($start, $end): void {
                        $outer->where(function ($vis): void {
                            $vis->where('is_available', true)->where('is_published', true);
                        })->orWhereHas('stockInflows', function ($iq) use ($start, $end): void {
                            $this->scopeInflowsNearWindow($iq, $start, $end);
                        });
                    });
                },
                function ($q) use ($start, $end): void {
                    $q->whereHas('stockInflows', function ($iq) use ($start, $end): void {
                        $this->scopeInflowsNearWindow($iq, $start, $end);
                    });
                },
            )
            ->when($productFilter !== '', function ($q) use ($productFilter): void {
                $needle = '%'.mb_strtolower($productFilter).'%';
                $q->whereHas('translations', function ($tq) use ($needle): void {
                    $tq->whereRaw('LOWER(name) LIKE ?', [$needle]);
                });
            })
            ->when($producerFilter > 0, function ($q) use ($producerFilter): void {
                $q->whereHas('producers', function ($pq) use ($producerFilter): void {
                    $pq->where('producers.id', $producerFilter);
                });
            })
            ->get();

        $isoDates = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $isoDates[] = $cursor->toDateString();
        }

        // Without a name search, drop candidates that have neither remaining
        // stock nor an inflow received inside the visible window.
        if (! $searchingByName && $candidates->isNotEmpty()) {
            $consumerBulk = $this->inventory->availabilityMatrix($candidates, $start, $end);
            $b2bBulk = $this->inventory->availabilityMatrix(
                $candidates,
                $start,
                $end,
                ShopStockAudience::BusinessTrade,
            );
            $inflowInWindow = ProductStockInflow::query()
                ->whereBetween('received_on', [$start->toDateString(), $end->toDateString()])
                ->whereIn('product_id', $candidates->pluck('id')->all())
                ->distinct()
                ->pluck('product_id')
                ->flip()
                ->all();

            $candidates = $candidates
                ->filter(function (Product $product) use ($consumerBulk, $b2bBulk, $inflowInWindow, $isoDates): bool {
                    if (isset($inflowInWindow[$product->id])) {
                        return true;
                    }
                    foreach ($isoDates as $iso) {
                        if (($consumerBulk[$product->id][$iso] ?? 0) > 0
                            || ($b2bBulk[$product->id][$iso] ?? 0) > 0) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values();
        }

        $sorted = InventoryProductClusters::sorted($candidates);
        $productPaginator = InventoryProductClusters::paginate(
            $sorted,
            (int) $request->query('page', 1),
        )->withQueryString();

        $pageIds = $productPaginator->getCollection()->pluck('id')->all();
        $products = $pageIds === []
            ? collect()
            : Product::query()
                ->with([
                    'translations',
                    'producer.translations',
                    'producers.translations',
                    'variants',
                    'stockInflowSchedules',
                ])
                ->whereIn('id', $pageIds)
                ->get()
                ->sortBy(fn (Product $p) => array_search($p->id, $pageIds, true))
                ->values();

        // Bulk + cached windows for the current page only (not one query per row).
        $availabilityMatrix = $this->denseAvailabilityMatrix(
            $this->inventory->availabilityMatrix($products, $start, $end),
            $products,
            $isoDates,
        );
        $b2bAvailabilityMatrix = $this->denseAvailabilityMatrix(
            $this->inventory->availabilityMatrix($products, $start, $end, ShopStockAudience::BusinessTrade),
            $products,
            $isoDates,
        );

        // Per-grade lanes for products that have grades, so the matrix shows
        // each grade's own stock under the product total. productId =>
        // (variantKey => (iso => qty)); variantKey 0 = the default grade.
        $variantMatrix = $products
            ->filter(fn (Product $p): bool => $p->variants->isNotEmpty())
            ->mapWithKeys(fn (Product $p): array => [$p->id => $this->inventory->variantAvailabilityWindow($p, $start, $end)]);

        // Per-farm day quantities for products stocked by SEVERAL farms:
        // the product row keeps the sum, the expandable sub-rows show each
        // farm's remaining (same end-state semantics as the matrix). vkeys
        // are summed; grade-level farm splits stay out on purpose.
        $pids = $products->pluck('id')->all();
        $farmConsumer = $this->allocation->remainingByProducer($pids, $isoDates);
        $farmB2b = $this->allocation->remainingByProducer($pids, $isoDates, ShopStockAudience::BusinessTrade);

        // Farm moves (producer position overrides) reattribute stock between
        // products on the inventar page only: the source product regains the
        // moved position (+qty), the target product is charged (−qty). Layer
        // that signed delta onto both windows so the product TOTALS track the
        // per-farm remaining above. Storefront/B2B availability stays untouched.
        $availabilityDelta = $this->allocation->overrideAvailabilityDelta($pids, $isoDates);
        if ($availabilityDelta !== []) {
            $applyDelta = fn ($matrix) => $matrix->map(function (array $window, int $pid) use ($availabilityDelta): array {
                foreach ($window as $iso => $qty) {
                    $d = $availabilityDelta[$iso][$pid] ?? 0.0;
                    if ($d !== 0.0) {
                        $window[$iso] = max(0.0, (float) $qty + $d);
                    }
                }

                return $window;
            });
            $availabilityMatrix = $applyDelta($availabilityMatrix);
            $b2bAvailabilityMatrix = $applyDelta($b2bAvailabilityMatrix);
        }

        $farmMatrix = [];      // [pid][producerId][iso] => ['qty' => float, 'b2bExtra' => float]
        $farmTotals = [];      // [pid][producerId] => window total, for ordering
        foreach ($pids as $pid) {
            foreach ($isoDates as $iso) {
                $consumerShares = ProducerAllocationService::unionShares($farmConsumer[$iso][$pid] ?? []);
                $b2bShares = ProducerAllocationService::unionShares($farmB2b[$iso][$pid] ?? []);
                foreach (array_keys($b2bShares + $consumerShares) as $producerId) {
                    $qty = $consumerShares[$producerId] ?? 0.0;
                    $extra = max(0.0, ($b2bShares[$producerId] ?? 0.0) - $qty);
                    if ($qty <= 1e-9 && $extra <= 1e-9) {
                        continue;
                    }
                    $farmMatrix[$pid][$producerId][$iso] = ['qty' => $qty, 'b2bExtra' => $extra];
                    $farmTotals[$pid][$producerId] = ($farmTotals[$pid][$producerId] ?? 0.0) + $qty + $extra;
                }
            }
        }
        $multiFarmProducts = [];
        foreach ($farmMatrix as $pid => $byProducer) {
            if (count($byProducer) > 1) {
                $multiFarmProducts[$pid] = true;
                arsort($farmTotals[$pid]);
                $farmMatrix[$pid] = array_replace(array_fill_keys(array_keys($farmTotals[$pid]), []), $farmMatrix[$pid]);
            }
        }
        $farmNames = [];
        foreach ($products as $product) {
            foreach ($product->producers as $producer) {
                $farmNames[$producer->id] = (string) $producer->name;
            }
            if ($product->producer !== null) {
                $farmNames[$product->producer->id] ??= (string) $product->producer->name;
            }
        }

        // Inflow batches in the window — same page-level filter as the matrix
        // above, plus a fallback on the batch's own producer_id so a delivery
        // from a non-registered supplier still matches when the admin filters
        // by that farm.
        $inflows = ProductStockInflow::query()
            ->whereBetween('received_on', [$start->toDateString(), $end->toDateString()])
            ->when($productFilter !== '', function ($q) use ($productFilter): void {
                $needle = '%'.mb_strtolower($productFilter).'%';
                $q->whereHas('product.translations', function ($tq) use ($needle): void {
                    $tq->whereRaw('LOWER(name) LIKE ?', [$needle]);
                });
            })
            ->when($producerFilter > 0, function ($q) use ($producerFilter): void {
                $q->where(function ($or) use ($producerFilter): void {
                    $or->where('producer_id', $producerFilter)
                        ->orWhereHas('product.producers', function ($pq) use ($producerFilter): void {
                            $pq->where('producers.id', $producerFilter);
                        });
                });
            })
            ->with(['product.translations', 'product.producer.translations', 'product.producers.translations', 'product.variants', 'producer.translations', 'sourceSchedule'])
            ->orderBy('received_on')
            ->get();

        // Dropdown for the producer filter — every producer, ordered by
        // localized name for the current locale.
        $producerOptions = Producer::query()
            ->with('translations')
            ->get()
            ->sortBy(fn (Producer $p) => mb_strtolower((string) $p->name), SORT_NATURAL)
            ->values();

        // Preserve the filter values on window navigation links so stepping
        // forward/backward doesn't drop them.
        $navQuery = array_filter([
            'product' => $productFilter !== '' ? $productFilter : null,
            'producer' => $producerFilter > 0 ? $producerFilter : null,
        ], fn ($v) => $v !== null);

        return view('admin.inventory.index', [
            'start' => $start,
            'end' => $end,
            'previousWindow' => $start->copy()->subDays(14)->toDateString(),
            'nextWindow' => $start->copy()->addDays(14)->toDateString(),
            'today' => Carbon::today()->toDateString(),
            'products' => $products,
            'productPaginator' => $productPaginator,
            'productsTotal' => $sorted->count(),
            'searchingByName' => $searchingByName,
            'availabilityMatrix' => $availabilityMatrix,
            'b2bAvailabilityMatrix' => $b2bAvailabilityMatrix,
            'variantMatrix' => $variantMatrix,
            'farmMatrix' => $farmMatrix,
            'multiFarmProducts' => $multiFarmProducts,
            'farmNames' => $farmNames,
            'inflows' => $inflows,
            'productFilter' => $productFilter,
            'producerFilter' => $producerFilter,
            'producerOptions' => $producerOptions,
            'navQuery' => $navQuery,
        ]);
    }

    /**
     * Inflows that can still affect availability in [start, end]: received on
     * or before the window end, and not older than the max freshness window.
     *
     * @param  Builder<ProductStockInflow>|Relation  $query
     */
    private function scopeInflowsNearWindow($query, Carbon $start, Carbon $end): void
    {
        $query
            ->where('received_on', '<=', $end->toDateString())
            ->where('received_on', '>=', $start->copy()->subDays(60)->toDateString());
    }

    /**
     * @param  array<int, array<string, float>>  $sparse
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $isoDates
     * @return Collection<int, array<string, float>>
     */
    private function denseAvailabilityMatrix(array $sparse, Collection $products, array $isoDates): Collection
    {
        return $products->mapWithKeys(function (Product $product) use ($sparse, $isoDates): array {
            $row = [];
            foreach ($isoDates as $iso) {
                $row[$iso] = max(0.0, round((float) ($sparse[$product->id][$iso] ?? 0.0), 4));
            }

            return [$product->id => $row];
        });
    }

    public function storeInflow(AdminInventoryInflowStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $this->authorize('update', $product);

        $unit = $product->unit;
        $displayUnit = $product->price_display_unit ?? $unit?->defaultDisplayUnit();
        $factor = ($unit !== null && $displayUnit !== null)
            ? $unit->canonicalUnitsPerDisplay($displayUnit)
            : 1;

        $variantId = $this->resolveVariantId($product, $validated['variant_id'] ?? null);

        $this->inventory->recordInflow(
            product: $product,
            receivedOn: Carbon::parse($validated['received_on']),
            quantity: round((float) $validated['quantity'] * $factor, 4),
            freshnessWindowDays: $validated['freshness_window_days'] ?? null,
            sourceScheduleId: null,
            notes: $validated['notes'] ?? null,
            createdByUserId: (int) $request->user()->id,
            producerId: isset($validated['producer_id']) ? (int) $validated['producer_id'] : null,
            b2bOnly: (bool) ($validated['b2b_only'] ?? false),
            variantId: $variantId,
        );

        return $this->redirectToInventory($request, $validated['received_on'], __('admin.inventory.flash_inflow_created'));
    }

    public function updateInflow(AdminInventoryInflowUpdateRequest $request, ProductStockInflow $inflow): RedirectResponse
    {
        $this->authorize('update', $inflow->product);

        $product = $inflow->product;
        $unit = $product?->unit;
        $displayUnit = $product?->price_display_unit ?? $unit?->defaultDisplayUnit();
        $factor = ($unit !== null && $displayUnit !== null)
            ? $unit->canonicalUnitsPerDisplay($displayUnit)
            : 1;

        $validated = $request->validated();

        // `original_received_on` is intentionally NOT touched here —
        // it stays at whatever the materializer (or the recordInflow
        // path for one-offs) set, so the cron continues to recognise
        // this row as fulfilling its scheduled occurrence even after
        // an admin moves it to a different day.
        $inflow->fill([
            'received_on' => Carbon::parse($validated['received_on'])->startOfDay()->toDateString(),
            'variant_id' => $this->resolveVariantId($product, $validated['variant_id'] ?? null),
            'producer_id' => isset($validated['producer_id'])
                ? (int) $validated['producer_id']
                : ($inflow->producer_id ?? $product?->producer_id),
            'quantity' => round((float) $validated['quantity'] * $factor, 4),
            'freshness_window_days' => max(1, (int) (
                $validated['freshness_window_days']
                ?? $inflow->freshness_window_days
                ?? $product?->default_freshness_window_days
                ?? 1
            )),
            'b2b_only' => (bool) ($validated['b2b_only'] ?? false),
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return $this->redirectToInventory($request, $validated['received_on'], __('admin.inventory.flash_inflow_updated'));
    }

    public function destroyInflow(Request $request, ProductStockInflow $inflow): RedirectResponse
    {
        $this->authorize('update', $inflow->product);

        $receivedOn = $inflow->received_on?->toDateString();

        $inflow->delete();

        return $this->redirectToInventory($request, $receivedOn, __('admin.inventory.flash_inflow_deleted'));
    }

    /**
     * Validate that a chosen grade belongs to the product (and is real),
     * returning its id or NULL for the default grade. Rejects a variant from
     * another product so an inflow can't be mis-attributed.
     */
    private function resolveVariantId(?Product $product, int|string|null $variantId): ?int
    {
        if ($variantId === null || $variantId === '' || $product === null) {
            return null;
        }

        $belongs = $product->variants()->whereKey((int) $variantId)->exists();
        abort_unless($belongs, 422, 'The selected grade does not belong to this product.');

        return (int) $variantId;
    }

    private function redirectToInventory(Request $request, ?string $contextDate, string $flash): RedirectResponse
    {
        $target = $request->input('_redirect');
        if (! is_string($target) || $target === '') {
            $target = route('app.admin.inventory.index', $contextDate ? ['from' => $contextDate] : []);
        }

        return redirect($target)->with('status', $flash);
    }
}
