<?php

namespace App\Services\Admin;

use App\Enums\CustomerType;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ProductProducerVariantPrice;

/**
 * Snapshots a product's current list prices into product_price_histories.
 * Called after every admin product save (and by the backfill command);
 * a row is appended only when a series' value actually changed, so calling
 * this often is cheap and idempotent.
 *
 * Series (all € per DISPLAY unit):
 *   base_gross    B2C sales price incl. VAT, product + each grade with its own price
 *   business_net  B2B list price excl. VAT, product + each grade with its own rule
 *   buying        purchase price: product-level (producer null), per farm, per farm+grade
 *
 * Date-window overrides and quantity tiers are deliberately NOT tracked:
 * they are temporary promotions, not list price development.
 */
class PriceHistoryRecorder
{
    public function record(Product $product): void
    {
        $factor = $this->displayFactor($product);
        $now = now();

        $series = [];

        // ── B2C sales prices (gross) ────────────────────────────────────
        if ($product->unit_price !== null) {
            $series[] = ['kind' => ProductPriceHistory::KIND_BASE_GROSS, 'variant_id' => null, 'producer_id' => null,
                'price' => round((float) $product->unit_price * $factor, 4)];
        }
        foreach ($product->variants as $variant) {
            if ($variant->unit_price !== null) {
                $series[] = ['kind' => ProductPriceHistory::KIND_BASE_GROSS, 'variant_id' => $variant->id, 'producer_id' => null,
                    'price' => round((float) $variant->unit_price * $factor, 4)];
            }
        }

        // ── B2B list prices (net): the plain business rules ─────────────
        $businessRules = $product->priceRules()
            ->where('customer_type', CustomerType::Business->value)
            ->whereNull('starts_on')->whereNull('ends_on')->whereNull('min_quantity')
            ->where('is_active', true)
            ->get();
        foreach ($businessRules as $rule) {
            $series[] = ['kind' => ProductPriceHistory::KIND_BUSINESS_NET, 'variant_id' => $rule->variant_id, 'producer_id' => null,
                'price' => round((float) $rule->unit_price * $factor, 4)];
        }

        // ── Buying prices (already stored in display-unit cents) ────────
        if ($product->buying_price_cents !== null) {
            $series[] = ['kind' => ProductPriceHistory::KIND_BUYING, 'variant_id' => null, 'producer_id' => null,
                'price' => round(((int) $product->buying_price_cents) / 100, 4)];
        }
        foreach ($product->producers as $producer) {
            if ($producer->pivot->buying_price_cents !== null) {
                $series[] = ['kind' => ProductPriceHistory::KIND_BUYING, 'variant_id' => null, 'producer_id' => $producer->id,
                    'price' => round(((int) $producer->pivot->buying_price_cents) / 100, 4)];
            }
        }
        $gradePrices = ProductProducerVariantPrice::query()->where('product_id', $product->id)->get();
        foreach ($gradePrices as $row) {
            $series[] = ['kind' => ProductPriceHistory::KIND_BUYING, 'variant_id' => $row->variant_id, 'producer_id' => $row->producer_id,
                'price' => round(((int) $row->buying_price_cents) / 100, 4)];
        }

        // ── Append only what changed ────────────────────────────────────
        $latest = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->keyBy(fn (ProductPriceHistory $h) => $h->kind.'|'.($h->variant_id ?? 0).'|'.($h->producer_id ?? 0));

        foreach ($series as $point) {
            $key = $point['kind'].'|'.($point['variant_id'] ?? 0).'|'.($point['producer_id'] ?? 0);
            $last = $latest->get($key);
            if ($last !== null && abs((float) $last->price - $point['price']) < 0.00005) {
                continue;
            }

            ProductPriceHistory::create($point + ['product_id' => $product->id, 'recorded_at' => $now]);
        }
    }

    /** Canonical units per display unit (g→kg = 1000 …), same as the product form. */
    private function displayFactor(Product $product): int
    {
        if ($product->unit === null) {
            return 1;
        }
        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
        $factor = $product->unit->canonicalUnitsPerDisplay($displayUnit);

        return $factor > 0 ? $factor : 1;
    }
}
