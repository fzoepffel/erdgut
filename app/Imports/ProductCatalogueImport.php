<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\InventoryService;
use App\Services\Admin\ProductWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies a parsed catalogue plan ({@see ProductCsvParser::parse()}) to the
 * database in a single transaction: each product is upserted through the
 * shared {@see ProductWriter}, then its daily-availability intents (Module B)
 * are written as one-off stock inflows.
 *
 * Availability semantics: a dated column value is the authoritative single
 * (non-schedule) inflow quantity for that product on that day. Applying it
 * consolidates every existing one-off inflow on that day into a single
 * CSV-owned row set to the value; a blank cell means "no change".
 *
 * Guard rail: daily writes only ever touch one-off inflows (those with no
 * `source_schedule_id`). Schedule-materialized recurring batches are never
 * read or mutated, so recurring schedules and their FEFO allocations stay
 * intact.
 */
class ProductCatalogueImport
{
    /** Marks an inflow as owned by the catalogue CSV so re-imports update it. */
    public const CSV_NOTE = 'csv-catalogue';

    public function __construct(
        private readonly ProductWriter $writer,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $plannedProducts  the parser's `products`
     * @param  array<string, array{de:string, en:string, fr:string}>  $groupTranslations  lowercased new-group German name => localized names supplied in the import dialog
     * @param  array<string, array{de:string, en:string, fr:string}>  $subcategoryTranslations  lowercased new-subcategory label => localized labels supplied in the import dialog
     * @return array{created:int, updated:int, weekly:int}
     */
    public function apply(
        array $plannedProducts,
        ?int $userId = null,
        array $groupTranslations = [],
        array $subcategoryTranslations = [],
    ): array {
        $plannedProducts = $this->injectTranslations($plannedProducts, $groupTranslations, $subcategoryTranslations);

        return DB::transaction(function () use ($plannedProducts, $userId): array {
            $created = 0;
            $updated = 0;
            $weekly = 0;

            foreach ($plannedProducts as $plan) {
                $existing = $plan['product_id'] !== null
                    ? Product::query()->find($plan['product_id'])
                    : null;

                $product = $this->writer->upsert($plan['data'], $existing, $userId);
                $existing === null ? $created++ : $updated++;

                $weekly += $this->applyDaily($product, $plan['weekly'] ?? [], $userId);
            }

            return ['created' => $created, 'updated' => $updated, 'weekly' => $weekly];
        });
    }

    /**
     * Fold the dialog-supplied localized labels into each product plan so a new
     * group is created with all three names and a new subcategory with all
     * three labels (instead of a German-only fallback). Existing groups and
     * subcategories are unaffected: their labels are never in these maps.
     *
     * @param  list<array<string, mixed>>  $plannedProducts
     * @param  array<string, array{de:string, en:string, fr:string}>  $groupTranslations
     * @param  array<string, array{de:string, en:string, fr:string}>  $subcategoryTranslations
     * @return list<array<string, mixed>>
     */
    private function injectTranslations(array $plannedProducts, array $groupTranslations, array $subcategoryTranslations): array
    {
        if ($groupTranslations === [] && $subcategoryTranslations === []) {
            return $plannedProducts;
        }

        foreach ($plannedProducts as &$plan) {
            $data = $plan['data'];

            $groupDe = mb_strtolower(trim((string) ($data['group_name']['de'] ?? '')));
            if ($groupDe !== '' && isset($groupTranslations[$groupDe])) {
                $data['group_name'] = $groupTranslations[$groupDe];
            }

            $subLabel = mb_strtolower(trim((string) ($data['subcategory_label'] ?? '')));
            if ($subLabel !== '' && isset($subcategoryTranslations[$subLabel])) {
                $data['subcategory_new'] = $subcategoryTranslations[$subLabel];
            }

            $plan['data'] = $data;
        }
        unset($plan);

        return $plannedProducts;
    }

    /**
     * Apply the product's daily intents. Each intent makes the value the total
     * single-inflow quantity for that product on that day: existing one-off
     * inflows on the day are consolidated into a single CSV-owned row set to
     * the value. Schedule-materialized batches are never touched.
     *
     * @param  list<array{date:string, canonical:float, raw:string}>  $daily
     */
    private function applyDaily(Product $product, array $daily, ?int $userId): int
    {
        $count = 0;
        foreach ($daily as $intent) {
            $date = Carbon::parse($intent['date'])->startOfDay();
            $quantity = round((float) $intent['canonical'], 4);
            $freshness = max(1, (int) ($product->default_freshness_window_days ?? 1));

            // Every one-off inflow on this day (any origin except a schedule),
            // oldest first. The first becomes the row we keep and adjust; the
            // rest are folded into it and removed.
            $oneOffs = ProductStockInflow::query()
                ->where('product_id', $product->id)
                ->whereNull('variant_id')
                ->whereNull('source_schedule_id')
                ->whereDate('received_on', $date->toDateString())
                ->orderBy('id')
                ->get();

            $anchor = $oneOffs->firstWhere('notes', self::CSV_NOTE) ?? $oneOffs->first();

            if ($anchor !== null) {
                $anchor->update([
                    'quantity' => $quantity,
                    'freshness_window_days' => $freshness,
                    'producer_id' => $product->producer_id,
                    'notes' => self::CSV_NOTE,
                ]);
                $oneOffs->reject(fn (ProductStockInflow $i): bool => $i->id === $anchor->id)
                    ->each(fn (ProductStockInflow $i) => $i->delete());
            } else {
                $this->inventory->recordInflow(
                    product: $product,
                    receivedOn: $date,
                    quantity: $quantity,
                    freshnessWindowDays: $freshness,
                    sourceScheduleId: null,
                    notes: self::CSV_NOTE,
                    createdByUserId: $userId,
                    producerId: $product->producer_id,
                );
            }
            $count++;
        }

        return $count;
    }
}
