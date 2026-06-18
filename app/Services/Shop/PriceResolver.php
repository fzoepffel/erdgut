<?php

namespace App\Services\Shop;

use App\Enums\CustomerType;
use App\Models\Product;
use App\Models\ProductPriceRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the effective gross unit price (per canonical unit) for a product,
 * given a delivery date, customer type and ordered quantity.
 *
 * Precedence (designed for all phases; this phase exercises base + period
 * overrides + customer-group prices + the margin floor):
 *   1. Candidate rules: active, date in window, customer_type matches or null,
 *      min_quantity <= qty or null, variant matches or null.
 *   2. Winner: highest priority, then most specific (customer_type set, then
 *      date-bounded), then narrowest window, then highest min_quantity.
 *   3. Fall back to products.unit_price when no rule matches.
 *   4. Clamp to the margin floor (buying price + configured minimum margin).
 */
class PriceResolver
{
    public function resolve(
        Product $product,
        ?Carbon $date = null,
        CustomerType $customerType = CustomerType::Private,
        ?float $quantity = null,
        ?int $variantId = null,
    ): ResolvedPrice {
        $date ??= Carbon::today();

        // Use eager-loaded rules when present to avoid N+1 in cart/grid loops.
        $rules = $product->relationLoaded('priceRules')
            ? $product->priceRules
            : $product->priceRules()->get();

        $candidates = $rules->filter(function (ProductPriceRule $rule) use ($date, $customerType, $quantity, $variantId): bool {
            if (! $rule->is_active) {
                return false;
            }
            if ($rule->starts_on !== null && $rule->starts_on->gt($date)) {
                return false;
            }
            if ($rule->ends_on !== null && $rule->ends_on->lt($date)) {
                return false;
            }
            if ($rule->customer_type !== null && $rule->customer_type !== $customerType) {
                return false;
            }
            if ($rule->variant_id !== null && $rule->variant_id !== $variantId) {
                return false;
            }
            if ($rule->min_quantity !== null && ($quantity === null || $quantity + 1e-9 < (float) $rule->min_quantity)) {
                return false;
            }

            return true;
        });

        $winner = $candidates->sort(fn (ProductPriceRule $a, ProductPriceRule $b): int => $this->compare($a, $b))->first();

        if ($winner !== null) {
            $price = (float) $winner->unit_price;
            $reason = $this->reasonFor($winner);
            $ruleId = (int) $winner->id;
        } else {
            $price = (float) $product->unit_price;
            $reason = 'base';
            $ruleId = null;
        }

        // Margin floor guardrail: never resolve below buying price + min margin.
        $floor = $this->marginFloor($product);
        if ($floor !== null && $price < $floor) {
            Log::warning('PriceResolver clamped a price to the margin floor.', [
                'product_id' => $product->id,
                'resolved' => $price,
                'floor' => $floor,
                'rule_id' => $ruleId,
            ]);

            return new ResolvedPrice($floor, 'margin_floor', $ruleId);
        }

        return new ResolvedPrice(round($price, 4), $reason, $ruleId);
    }

    /**
     * Sort comparator: lower return value sorts first (the winner).
     * Higher priority wins; then more-specific; then narrower window.
     */
    private function compare(ProductPriceRule $a, ProductPriceRule $b): int
    {
        if ($a->priority !== $b->priority) {
            return $b->priority <=> $a->priority;
        }
        if ($this->specificity($a) !== $this->specificity($b)) {
            return $this->specificity($b) <=> $this->specificity($a);
        }

        return $this->windowDays($a) <=> $this->windowDays($b);
    }

    private function specificity(ProductPriceRule $rule): int
    {
        return ($rule->customer_type !== null ? 2 : 0)
            + ($rule->starts_on !== null || $rule->ends_on !== null ? 1 : 0)
            + ($rule->min_quantity !== null ? 1 : 0)
            + ($rule->variant_id !== null ? 1 : 0);
    }

    /** Bounded windows sort ahead of open-ended ones; PHP_INT_MAX for open. */
    private function windowDays(ProductPriceRule $rule): int
    {
        if ($rule->starts_on === null || $rule->ends_on === null) {
            return PHP_INT_MAX;
        }

        return (int) $rule->starts_on->diffInDays($rule->ends_on);
    }

    private function reasonFor(ProductPriceRule $rule): string
    {
        if ($rule->customer_type !== null) {
            return 'customer_group';
        }
        if ($rule->min_quantity !== null) {
            return 'quantity_tier';
        }

        return 'period_override';
    }

    /** Buying price (gross-equivalent) plus configured minimum margin, or null. */
    private function marginFloor(Product $product): ?float
    {
        $buyingCents = $product->buying_price_cents;
        if ($buyingCents === null) {
            return null;
        }

        // buying_price_cents is per display unit; convert to per-canonical to
        // match unit_price. Falls back to raw when unit conversion is absent.
        $perCanonical = (float) $buyingCents / 100.0;
        if ($product->unit !== null) {
            $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
            $factor = $product->unit->canonicalUnitsPerDisplay($displayUnit);
            if ($factor > 0) {
                $perCanonical = ((float) $buyingCents / 100.0) / $factor;
            }
        }

        $minMargin = (float) config('shop.price_min_margin', 0.0);

        return $perCanonical * (1.0 + max(0.0, $minMargin));
    }
}
