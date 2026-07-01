<?php

namespace App\Services\Restaurant;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Enums\ShopStockAudience;
use App\Models\Product;
use App\Services\Admin\InventoryService;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopClosureService;
use App\Services\Shop\ShopOrderService;
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
            ->with(['translations', 'producer.translations', 'images', 'variants', 'priceRules'])
            ->get();

        $matrix = $this->inventory->availabilityMatrix($products, $date, $date, ShopStockAudience::BusinessTrade);
        $iso = $date->toDateString();

        return $products
            ->map(function (Product $product) use ($matrix, $iso, $date): ?array {
                $remaining = (float) ($matrix[$product->id][$iso] ?? 0.0);
                $inStock = $remaining > 0;
                // The "on request" track is for genuinely seasonal items that
                // are currently in their window but out of stock. Non-seasonal
                // (year-round) products show only when actually in stock, so the
                // catalog isn't flooded with always-"on request" staples.
                $inSeason = $product->is_seasonal && $product->isInSeason($date);

                if (! $inStock && ! $inSeason) {
                    return null;
                }

                return $this->row($product, $date, $inStock, $inSeason);
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{
     *     product: Product, inStock: bool, inSeason: bool,
     *     grossPerDisplay: float, netPerDisplay: float, vatRate: int,
     *     displayUnit: string, priceFrom: bool
     * }
     */
    private function row(Product $product, Carbon $date, bool $inStock, bool $inSeason): array
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
            'grades' => $this->gradesFor($product, $date, $displayUnit, $factor, $vatRate, $inSeason),
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
     *     step:int, cap:?float, displayFactor:int, unitSymbol:string, unitKind:string}>
     */
    private function gradesFor(Product $product, Carbon $date, string $displayUnit, int $factor, int $vatRate, bool $inSeason): array
    {
        $step = max(1, $product->effectiveQuantityStep());
        $unitSymbol = ProductUnit::displaySymbol($displayUnit);
        $unitKind = $product->unit?->value ?? 'piece';

        $build = function (?int $variantId, ?string $label, float $grossCanonical) use ($product, $date, $factor, $vatRate, $step, $unitSymbol, $unitKind, $inSeason): ?array {
            $remaining = $this->inventory->remainingForGrade($product, $date, $variantId, ShopStockAudience::BusinessTrade);
            $cap = $inSeason ? null : floor($remaining / $step) * $step;
            if ($cap !== null && $cap <= 0) {
                return null; // no stock, not seasonal → not orderable
            }

            $grossPerDisplay = round($grossCanonical * $factor, 2);
            $netPerDisplay = round($grossPerDisplay - ShopOrderService::vatAmountFromGross($grossPerDisplay, $vatRate), 2);

            return [
                'key' => $variantId === null ? (string) $product->id : 'v'.$variantId,
                'variantId' => $variantId,
                'label' => $label,
                'netPerDisplay' => $netPerDisplay,
                'step' => $step,
                'cap' => $cap,
                'displayFactor' => $factor,
                'unitSymbol' => $unitSymbol,
                'unitKind' => $unitKind,
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
