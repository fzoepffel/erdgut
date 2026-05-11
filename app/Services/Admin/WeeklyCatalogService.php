<?php

namespace App\Services\Admin;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductWeekStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles reading + saving the weekly product catalog (variant 1).
 *
 * Each row in the catalog is either an existing product + planned quantity for
 * that week, or a brand new product created inline.
 */
final class WeeklyCatalogService
{
    /**
     * @return Collection<int, array{
     *     stock: ProductWeekStock,
     *     product: Product,
     *     distributed: float,
     *     remaining: float,
     *     over: bool,
     *     display_unit: string,
     *     display_quantity: float,
     *     display_distributed: float,
     *     display_remaining: float,
     *     conversion_factor: int,
     * }>
     */
    public function rowsForWeek(Carbon $week): Collection
    {
        $date = $week->copy()->startOfDay()->toDateString();

        /** @var Collection<int, ProductWeekStock> $stocks */
        $stocks = ProductWeekStock::query()
            ->whereDate('week_start_date', $date)
            ->with('product.translations')
            ->get()
            ->sortBy(fn (ProductWeekStock $s) => $s->product?->name ?? '')
            ->values();

        return $stocks
            ->map(function (ProductWeekStock $stock) use ($week): array {
                $product = $stock->product;
                $distributed = $product !== null
                    ? $product->distributedQuantityForWeek($week)
                    : 0.0;
                $planned = (float) $stock->planned_quantity;
                $remaining = round($planned - $distributed, 2);

                // The input/display side works in the product's chosen
                // display unit. Convert canonical-stored quantities to
                // display units for both the input pre-fill and the
                // remaining-quantity column.
                $displayUnit = $product?->price_display_unit
                    ?? $product?->unit?->defaultDisplayUnit()
                    ?? 'piece';
                $factor = $product?->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;

                return [
                    'stock' => $stock,
                    'product' => $product,
                    'distributed' => $distributed,
                    'remaining' => $remaining,
                    'over' => $remaining < 0,
                    'display_unit' => $displayUnit,
                    'display_quantity' => round($planned / $factor, 4),
                    'display_distributed' => round($distributed / $factor, 4),
                    'display_remaining' => round($remaining / $factor, 4),
                    'conversion_factor' => $factor,
                ];
            })
            ->values();
    }

    /**
     * Persist the weekly catalog for the given Monday. Lines with `_delete=1`
     * remove only the weekly stock row; lines with `mode=new` create a Product
     * and a stock row; `mode=existing` upserts the stock row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function saveForWeek(Carbon $week, array $rows): void
    {
        $date = $week->copy()->startOfDay()->toDateString();

        DB::transaction(function () use ($rows, $date): void {
            foreach ($rows as $row) {
                $mode = $row['mode'] ?? null;

                if (! empty($row['_delete']) && ! empty($row['stock_id'])) {
                    ProductWeekStock::query()
                        ->whereKey((int) $row['stock_id'])
                        ->delete();

                    continue;
                }

                $unit = isset($row['unit']) && $row['unit'] !== '' ? (string) $row['unit'] : null;
                $rawQuantity = round((float) ($row['quantity'] ?? 0), 4);

                if ($mode === 'new') {
                    $new = is_array($row['new'] ?? null) ? $row['new'] : [];
                    $priceCents = (int) ($new['unit_price_cents'] ?? 0);

                    // For mode=new, the row's `unit` is the new Product's
                    // kind (mass/volume/piece/bunch). Fall back to piece
                    // when something invalid sneaks through.
                    $productUnit = in_array($unit, ProductUnit::values(), true)
                        ? $unit
                        : ProductUnit::Piece->value;
                    $unitEnum = ProductUnit::from($productUnit);
                    $displayUnit = $unitEnum->defaultDisplayUnit();
                    $factor = $unitEnum->canonicalUnitsPerDisplay($displayUnit);

                    // The cents field is admin-entered in the display unit
                    // (cents per kg / per L / per piece / per bunch). Store
                    // canonical (cents per g / per ml / per piece / per bunch).
                    $unitPrice = round(($priceCents / 100) / $factor, 4);
                    $canonicalQuantity = round($rawQuantity * $factor, 4);

                    $product = Product::create([
                        'producer_id' => isset($new['producer_id']) && $new['producer_id'] !== ''
                            ? (int) $new['producer_id']
                            : null,
                        'category' => (string) ($new['category'] ?? ''),
                        'unit' => $productUnit,
                        'price_display_unit' => $displayUnit,
                        'unit_price' => $unitPrice,
                        'is_seasonal' => false,
                        'is_available' => true,
                        'is_published' => isset($new['is_published']) ? (bool) $new['is_published'] : true,
                        'metadata' => null,
                        'is_test' => false,
                    ]);

                    $name = (string) ($new['name'] ?? '');
                    $defaults = [
                        'origin' => 'regional',
                        'variety' => '-',
                        'taste_profile' => '-',
                    ];
                    foreach (['de', 'en', 'fr'] as $locale) {
                        ProductTranslation::query()->create([
                            'product_id' => $product->id,
                            'locale' => $locale,
                            'name' => $name,
                            'origin' => $defaults['origin'],
                            'variety' => $defaults['variety'],
                            'taste_profile' => $defaults['taste_profile'],
                        ]);
                    }

                    ProductWeekStock::create([
                        'product_id' => $product->id,
                        'week_start_date' => $date,
                        'planned_quantity' => $canonicalQuantity,
                    ]);

                    continue;
                }

                if ($mode === 'existing' && ! empty($row['product_id'])) {
                    $product = Product::find((int) $row['product_id']);
                    $unitEnum = $product?->unit;
                    $factor = 1;
                    if ($unitEnum !== null) {
                        $displayUnit = $product->price_display_unit ?? $unitEnum->defaultDisplayUnit();
                        $factor = $unitEnum->canonicalUnitsPerDisplay($displayUnit);
                    }
                    $canonicalQuantity = round($rawQuantity * $factor, 4);

                    $stock = ProductWeekStock::query()
                        ->where('product_id', (int) $row['product_id'])
                        ->whereDate('week_start_date', $date)
                        ->first();

                    if ($stock === null) {
                        ProductWeekStock::create([
                            'product_id' => (int) $row['product_id'],
                            'week_start_date' => $date,
                            'planned_quantity' => $canonicalQuantity,
                        ]);
                    } else {
                        $stock->fill([
                            'planned_quantity' => $canonicalQuantity,
                        ])->save();
                    }
                }
            }
        });
    }
}
