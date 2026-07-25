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
 * shared {@see ProductWriter}, then its weekly-availability intents (Module B)
 * are written as CSV-owned one-off stock inflows.
 *
 * Guard rail: weekly writes only ever touch this importer's OWN inflows
 * (a one-off receipt on that Monday tagged {@see CSV_NOTE}, with no
 * `source_schedule_id`). Schedule-materialized batches and manually-entered
 * inflows are never read or mutated, so recurring schedules and any committed
 * FEFO allocations stay intact.
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
     * @return array{created:int, updated:int, weekly:int}
     */
    public function apply(array $plannedProducts, ?int $userId = null): array
    {
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

                $weekly += $this->applyWeekly($product, $plan['weekly'] ?? [], $userId);
            }

            return ['created' => $created, 'updated' => $updated, 'weekly' => $weekly];
        });
    }

    /**
     * Write the product's weekly intents as CSV-owned one-off inflows.
     *
     * @param  list<array{monday:string, canonical:float, raw:string}>  $weekly
     */
    private function applyWeekly(Product $product, array $weekly, ?int $userId): int
    {
        $count = 0;
        foreach ($weekly as $intent) {
            $monday = Carbon::parse($intent['monday'])->startOfDay();
            $quantity = round((float) $intent['canonical'], 4);

            $existing = ProductStockInflow::query()
                ->where('product_id', $product->id)
                ->whereNull('variant_id')
                ->whereNull('source_schedule_id')
                ->where('notes', self::CSV_NOTE)
                ->whereDate('received_on', $monday->toDateString())
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'quantity' => $quantity,
                    'freshness_window_days' => max(1, (int) ($product->default_freshness_window_days ?? 1)),
                    'producer_id' => $product->producer_id,
                ]);
            } else {
                $this->inventory->recordInflow(
                    product: $product,
                    receivedOn: $monday,
                    quantity: $quantity,
                    freshnessWindowDays: (int) ($product->default_freshness_window_days ?? 1),
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
