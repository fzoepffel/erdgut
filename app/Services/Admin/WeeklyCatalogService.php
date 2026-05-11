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

                return [
                    'stock' => $stock,
                    'product' => $product,
                    'distributed' => $distributed,
                    'remaining' => $remaining,
                    'over' => $remaining < 0,
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
                $quantity = round((float) ($row['quantity'] ?? 0), 2);

                if ($mode === 'new') {
                    $new = is_array($row['new'] ?? null) ? $row['new'] : [];
                    $priceCents = (int) ($new['unit_price_cents'] ?? 0);
                    $unitPrice = round($priceCents / 100, 2);

                    // Map the freeform-ish per-week unit input to the new
                    // ProductUnit enum. If admin typed something unknown
                    // we default to `piece` rather than blocking the
                    // inline create; the admin can fix it on the product
                    // edit page.
                    $productUnit = in_array($unit, ProductUnit::values(), true)
                        ? $unit
                        : ProductUnit::Piece->value;

                    $product = Product::create([
                        'producer_id' => isset($new['producer_id']) && $new['producer_id'] !== ''
                            ? (int) $new['producer_id']
                            : null,
                        'category' => (string) ($new['category'] ?? ''),
                        'unit' => $productUnit,
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
                        'planned_quantity' => $quantity,
                        'unit' => $unit,
                    ]);

                    continue;
                }

                if ($mode === 'existing' && ! empty($row['product_id'])) {
                    $stock = ProductWeekStock::query()
                        ->where('product_id', (int) $row['product_id'])
                        ->whereDate('week_start_date', $date)
                        ->first();

                    if ($stock === null) {
                        ProductWeekStock::create([
                            'product_id' => (int) $row['product_id'],
                            'week_start_date' => $date,
                            'planned_quantity' => $quantity,
                            'unit' => $unit,
                        ]);
                    } else {
                        $stock->fill([
                            'planned_quantity' => $quantity,
                            'unit' => $unit,
                        ])->save();
                    }
                }
            }
        });
    }
}
