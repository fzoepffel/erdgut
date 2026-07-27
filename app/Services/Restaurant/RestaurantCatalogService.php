<?php

namespace App\Services\Restaurant;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Services\Admin\InventoryService;
use App\Services\Admin\ProducerAllocationService;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopClosureService;
use App\Services\Shop\ShopOrderService;
use App\Support\ProducerLabels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the restaurant (B2B) catalog: for a chosen delivery date, the products
 * a verified restaurant can order, priced at the business net price.
 *
 * The product set is "in stock for business trade OR in season" — so in-season
 * items surface even when temporarily out of stock (flagged "on request"). This
 * is read-only browsing; ordering/requests are a later ticket.
 */
class RestaurantCatalogService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PriceResolver $priceResolver,
        private readonly ShopClosureService $closures,
    ) {}

    /**
     * Open delivery days from tomorrow through the booking horizon, each with
     * its "orderable until" cutoff label (when a closure rule sets one) and the
     * count of products in stock that day. Closed days are excluded. In-season
     * "on request" items keep every open day valid, so days are NOT filtered by
     * stock — but the count lets the picker promote days that have stock.
     *
     * @return Collection<int, array{date: Carbon, cutoff_label: ?string, in_stock_count: int}>
     */
    public function selectableDates(): Collection
    {
        $start = Carbon::tomorrow();
        $weeks = (int) config('shop.horizon_weeks', 8);
        $end = $start->copy()->addWeeks(max(1, $weeks));

        $states = $this->closures->statesForRange($start, $end);

        // One matrix pass over the whole horizon (cached) → per-day in-stock count.
        $products = Product::query()->visibleInCatalog()->with('variants')->get();
        $matrix = $this->inventory->availabilityMatrix($products, $start, $end, ShopStockAudience::BusinessTrade);

        return collect($states)
            ->reject(fn (array $state): bool => $state['closed'])
            ->map(function (array $state, string $iso) use ($matrix, $products): array {
                $count = $products->filter(
                    fn (Product $p): bool => (float) ($matrix[$p->id][$iso] ?? 0.0) > 0.0
                )->count();

                return [
                    'date' => Carbon::parse($iso),
                    'cutoff_label' => $state['cutoff_label'],
                    'in_stock_count' => $count,
                ];
            })
            ->values();
    }

    /**
     * Upcoming weekly-delivery occurrences (same weekday as $selectedDate, from
     * next week through the horizon, open days only), each with the remaining
     * business stock per product so the "until" week picker can colour weeks
     * green (in stock) vs amber (on request).
     *
     * @return Collection<int, array{iso: string, kw: int, label: string, remainingByProduct: array<int, float>}>
     */
    public function recurrenceWeeks(Carbon $selectedDate): Collection
    {
        $selectedDate = $selectedDate->copy()->startOfDay();
        $weeks = (int) config('shop.horizon_weeks', 8);
        $start = $selectedDate->copy()->addWeek();
        $end = $selectedDate->copy()->addWeeks(max(1, $weeks));

        $products = Product::query()->visibleInCatalog()->with('variants')->get();
        $matrix = $this->inventory->availabilityMatrix($products, $start, $end, ShopStockAudience::BusinessTrade);
        $states = $this->closures->statesForRange($start, $end);
        $locale = app()->getLocale();

        $out = collect();
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addWeek()) {
            $iso = $cursor->toDateString();
            if (isset($states[$iso]) && $states[$iso]['closed']) {
                continue; // that weekday is closed this week — not deliverable
            }

            $remaining = [];
            foreach ($products as $product) {
                $remaining[$product->id] = (float) ($matrix[$product->id][$iso] ?? 0.0);
            }

            $out->push([
                'iso' => $iso,
                'kw' => $cursor->isoWeek(),
                'label' => $cursor->locale($locale)->isoFormat('dd, D. MMM'),
                'remainingByProduct' => $remaining,
            ]);
        }

        return $out->values();
    }

    /**
     * Catalog rows for a delivery date. Each row carries the business gross and
     * net per display unit, the VAT rate, and stock/season flags.
     *
     * @return Collection<int, array{
     *     product: Product, inStock: bool, inSeason: bool,
     *     grossPerDisplay: float, netPerDisplay: float, vatRate: int,
     *     displayUnit: string, priceFrom: bool
     * }>
     */
    public function productsForDate(Carbon $date): Collection
    {
        $date = $date->copy()->startOfDay();

        $products = Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations', 'producers.translations', 'producerVariantPrices', 'images', 'variants', 'priceRules', 'group'])
            ->get();

        $matrix = $this->inventory->availabilityMatrix($products, $date, $date, ShopStockAudience::BusinessTrade);
        $iso = $date->toDateString();

        // Farms with sellable B2B stock on the date (one batched, cached
        // call) — cards name these instead of the hardcoded primary.
        $producersOnDate = app(ProducerAllocationService::class)
            ->remainingByProducer($products->pluck('id')->all(), [$iso], ShopStockAudience::BusinessTrade)[$iso] ?? [];

        return $products
            ->map(function (Product $product) use ($matrix, $iso, $date, $producersOnDate): ?array {
                $remaining = (float) ($matrix[$product->id][$iso] ?? 0.0);
                $inStock = $remaining > 0;
                // The "on request" track: in-season items without stock show in
                // the Anfragen area by default; the per-product override can
                // force a product in ('always' — sourceable gap-fillers) or
                // out ('never') regardless of the season window.
                $visibility = $product->requestVisibilityOn($date);
                $inSeason = $product->is_seasonal && $product->isInSeason($date);
                $requestable = $visibility === 'always'
                    || ($inSeason && $visibility !== 'never');

                if (! $inStock && ! $requestable) {
                    return null;
                }

                $sharesByVkey = $producersOnDate[$product->id] ?? [];
                $row = $this->row($product, $date, $inStock, $requestable, $sharesByVkey);
                $row['producers'] = $this->producersForLabel(
                    $product,
                    $inStock,
                    ProducerAllocationService::unionShares($sharesByVkey),
                );
                $row['producerLabel'] = ProducerLabels::label($row['producers'])
                    ?: (string) ($product->producer?->name ?? '');

                return $row;
            })
            ->filter()
            ->pipe(fn (Collection $rows): Collection => $this->collapseGroupedRows($rows))
            ->values();
    }

    /**
     * Collapse rows sharing a ProductGroup into ONE catalog row: the group's
     * own name becomes the card title, grades are the concatenation of every
     * member's grades (each keeping ITS unit/step/cap/net price), and the
     * headline is the cheapest member with a "from" prefix. Members never
     * render as standalone rows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function collapseGroupedRows(Collection $rows): Collection
    {
        $byGroup = [];
        foreach ($rows as $row) {
            $groupId = $row['product']->group_id;
            if ($groupId !== null) {
                $byGroup[$groupId][] = $row;
            }
        }

        $out = collect();
        $emitted = [];
        foreach ($rows as $row) {
            $groupId = $row['product']->group_id;
            if ($groupId === null) {
                $out->push($row);

                continue;
            }
            if (isset($emitted[$groupId])) {
                continue;
            }
            $emitted[$groupId] = true;

            $memberRows = $byGroup[$groupId];
            usort($memberRows, fn (array $a, array $b): int => [$a['product']->group_position, $a['product']->id]
                <=> [$b['product']->group_position, $b['product']->id]);
            $lead = $memberRows[0];

            $grades = [];
            $producerLabels = [];
            $producerModels = [];
            $nets = [];
            foreach ($memberRows as $memberRow) {
                $member = $memberRow['product'];
                // Explicit group_label wins, otherwise the member PRODUCT's
                // name — the farm renders separately via producerLabel.
                $memberLabel = $member->localizedGroupLabel() !== ''
                    ? $member->localizedGroupLabel()
                    : (string) $member->name;

                foreach ($memberRow['grades'] as $grade) {
                    $grade['label'] = $grade['label'] === null
                        ? $memberLabel
                        : trim($memberLabel.' · '.$grade['label']);
                    $grades[] = $grade;
                    $nets[] = (float) $grade['netPerDisplay'];
                }
                if ((string) ($memberRow['producerLabel'] ?? '') !== '') {
                    $producerLabels[] = (string) $memberRow['producerLabel'];
                }
                foreach ($memberRow['producers'] ?? [] as $producer) {
                    $producerModels[$producer->id] = $producer;
                }
            }

            // Headline: the cheapest grade's member supplies net price AND
            // display unit (mixed-unit groups must not mix €/kg with €/Stück).
            $cheapest = $lead;
            foreach ($memberRows as $memberRow) {
                if ((float) $memberRow['netPerDisplay'] < (float) $cheapest['netPerDisplay']) {
                    $cheapest = $memberRow;
                }
            }

            $merged = $lead;
            // Card title: the group's own name — except when only ONE member
            // contributes grades, where that member's product name says
            // exactly what you buy.
            $membersWithGrades = array_values(array_filter($memberRows, fn (array $r): bool => $r['grades'] !== []));
            $merged['groupName'] = count($membersWithGrades) === 1
                ? (string) $membersWithGrades[0]['product']->name
                : ($lead['product']->group?->localizedName() ?: $lead['product']->name);
            $merged['inStock'] = (bool) array_reduce($memberRows, fn (bool $c, array $r): bool => $c || $r['inStock'], false);
            $merged['inSeason'] = (bool) array_reduce($memberRows, fn (bool $c, array $r): bool => $c || $r['inSeason'], false);
            $merged['available_on_selected'] = $merged['inStock'];
            $merged['netPerDisplay'] = $cheapest['netPerDisplay'];
            $merged['grossPerDisplay'] = $cheapest['grossPerDisplay'];
            $merged['displayUnit'] = $cheapest['displayUnit'];
            $merged['vatRate'] = $cheapest['vatRate'];
            $merged['priceFrom'] = count(array_unique(array_map(fn (float $n): string => number_format($n, 2), $nets))) > 1;
            $merged['grades'] = $grades;
            $merged['producers'] = collect(array_values($producerModels));
            $merged['producerLabel'] = implode(' · ', array_unique($producerLabels));

            $out->push($merged);
        }

        return $out;
    }

    /**
     * Farm label for ONE grade from its allocation shares (already qty
     * desc). Empty when the grade has no shares; the view then falls
     * back to the product-level label.
     *
     * @param  array<int, float>  $shares  [producerId => remainingQty]
     */
    private function gradeProducerLabel(Product $product, array $shares): string
    {
        if ($shares === []) {
            return '';
        }

        $namesById = [];
        foreach ($product->producers as $producer) {
            $namesById[$producer->id] = (string) $producer->name;
        }
        if ($product->producer !== null) {
            $namesById[$product->producer->id] ??= (string) $product->producer->name;
        }

        $names = [];
        foreach (array_keys($shares) as $producerId) {
            if (isset($namesById[$producerId])) {
                $names[] = $namesById[$producerId];
            }
        }

        return ProducerLabels::label($names);
    }

    /**
     * Farms shown on a catalog card, in label order. In-stock rows follow
     * allocation share (desc); request-track rows list every registered
     * supplier. Falls back to the primary producer.
     *
     * @param  array<int, float>  $shares  [producerId => remainingQty]
     * @return Collection<int, Producer>
     */
    private function producersForLabel(Product $product, bool $inStock, array $shares): Collection
    {
        $byId = [];
        foreach ($product->producers as $producer) {
            $byId[$producer->id] = $producer;
        }
        if ($product->producer !== null) {
            $byId[$product->producer->id] ??= $product->producer;
        }

        if ($inStock) {
            $ordered = collect();
            foreach (array_keys($shares) as $producerId) {
                if (isset($byId[$producerId])) {
                    $ordered->push($byId[$producerId]);
                }
            }
            if ($ordered->isNotEmpty()) {
                return $ordered;
            }
        }

        return collect(array_values($byId));
    }

    /**
     * @return array{
     *     product: Product, inStock: bool, inSeason: bool,
     *     grossPerDisplay: float, netPerDisplay: float, vatRate: int,
     *     displayUnit: string, priceFrom: bool
     * }
     */
    private function row(Product $product, Carbon $date, bool $inStock, bool $inSeason, array $sharesByVkey = []): array
    {
        $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';
        $factor = $product->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;

        // Cheapest active grade drives the headline price; the default grade is
        // the product itself (no variant). "from" prefix when grades vary.
        $variants = $product->activeVariants();
        $grossCanonicalCandidates = [
            $this->priceResolver->resolve($product, $date, CustomerType::Business)->unitPriceEur,
        ];
        foreach ($variants as $variant) {
            $grossCanonicalCandidates[] = $this->priceResolver
                ->resolve($product, $date, CustomerType::Business, null, $variant->id, $variant)
                ->unitPriceEur;
        }
        $grossCanonical = min($grossCanonicalCandidates);
        $priceFrom = count($grossCanonicalCandidates) > 1
            && max($grossCanonicalCandidates) !== min($grossCanonicalCandidates);

        $vatRate = (int) $product->vat_rate_percent;
        $grossPerDisplay = round($grossCanonical * $factor, 2);
        $netPerDisplay = round($grossPerDisplay - ShopOrderService::vatAmountFromGross($grossPerDisplay, $vatRate), 2);

        return [
            'product' => $product,
            'inStock' => $inStock,
            'inSeason' => $inSeason,
            // Key ProductRankingService reads to rank orderable rows first.
            'available_on_selected' => $inStock,
            'grossPerDisplay' => $grossPerDisplay,
            'netPerDisplay' => $netPerDisplay,
            'vatRate' => $vatRate,
            'displayUnit' => $displayUnit,
            'priceFrom' => $priceFrom,
            'grades' => $this->gradesFor($product, $date, $displayUnit, $factor, $vatRate, $inSeason, $sharesByVkey),
        ];
    }

    /**
     * Orderable grades for a product: the default grade plus each active
     * variant, each with its business net price per display unit, the canonical
     * quantity step, and a cap. In-stock grades cap at the grade's remaining
     * (floored to the step); seasonal products have no cap (over-ordering
     * becomes an "on request" line at submit). Grades with no stock on a
     * non-seasonal product are dropped (nothing to order).
     *
     * @return list<array{key:string, variantId:?int, label:?string, netPerDisplay:float,
     *     step:int, cap:?float, displayFactor:int, unitSymbol:string, unitKind:string,
     *     producerLabel:string}>
     */
    private function gradesFor(Product $product, Carbon $date, string $displayUnit, int $factor, int $vatRate, bool $inSeason, array $sharesByVkey = []): array
    {
        $step = max(1, $product->effectiveQuantityStep());
        $unitSymbol = $product->countUnitWord(1) ?? ProductUnit::displaySymbol($displayUnit);
        $unitKind = $product->unit?->value ?? 'piece';

        $build = function (?int $variantId, ?string $label, float $grossCanonical) use ($product, $date, $factor, $vatRate, $step, $unitSymbol, $unitKind, $inSeason, $sharesByVkey): ?array {
            $remaining = $this->inventory->remainingForGrade($product, $date, $variantId, ShopStockAudience::BusinessTrade);
            // `cap` is ALWAYS the real floored stock (the shop's hard maximum) —
            // including for seasonal products that happen to be in stock. A
            // seasonal grade with no stock keeps cap 0 (only requestable, and the
            // Anfragen side ignores the cap); a non-seasonal grade with no stock
            // is dropped entirely (nothing to order or request).
            $cap = floor($remaining / $step) * $step;
            if (! $inSeason && $cap <= 0) {
                return null;
            }

            $grossPerDisplay = round($grossCanonical * $factor, 2);
            $netPerDisplay = round($grossPerDisplay - ShopOrderService::vatAmountFromGross($grossPerDisplay, $vatRate), 2);

            return [
                'key' => $variantId === null ? (string) $product->id : 'v'.$variantId,
                'productId' => $product->id,
                'variantId' => $variantId,
                'label' => $label,
                'netPerDisplay' => $netPerDisplay,
                'step' => $step,
                'cap' => $cap,
                'displayFactor' => $factor,
                'unitSymbol' => $unitSymbol,
                'unitKind' => $unitKind,
                // Grade-exact farm label; empty shares (request track, no
                // batches) fall back to the product-level label in the view.
                'producerLabel' => $this->gradeProducerLabel($product, $sharesByVkey[$variantId ?? 0] ?? []),
            ];
        };

        $grades = [];
        $default = $build(null, null, $this->priceResolver->resolve($product, $date, CustomerType::Business)->unitPriceEur);
        if ($default !== null) {
            $grades[] = $default;
        }
        foreach ($product->activeVariants() as $variant) {
            $row = $build(
                $variant->id,
                $variant->localizedName(),
                $this->priceResolver->resolve($product, $date, CustomerType::Business, null, $variant->id, $variant)->unitPriceEur,
            );
            if ($row !== null) {
                $grades[] = $row;
            }
        }

        return $grades;
    }
}
