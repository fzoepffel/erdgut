<?php

namespace App\Services\Admin;

/** One row of the top-products chart (both channels merged, net revenue). */
final readonly class AdminOpsTopProduct
{
    public function __construct(
        public string $name,
        public string $producer,
        public float $qtyDisplay,
        public string $unitSymbol,
        public float $netRevenue,
        public float $share,
    ) {}
}
