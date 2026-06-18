<?php

namespace App\Services\Shop;

/**
 * The outcome of resolving a product's price for a given date / customer /
 * quantity. Carries provenance so the UI can explain the price and the order
 * line can snapshot which rule applied.
 */
final readonly class ResolvedPrice
{
    public function __construct(
        public float $unitPriceEur,
        public string $reason,
        public ?int $ruleId = null,
    ) {}
}
