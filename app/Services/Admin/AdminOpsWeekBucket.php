<?php

namespace App\Services\Admin;

/** One ISO week in the operations revenue chart (net euros per channel). */
final readonly class AdminOpsWeekBucket
{
    public function __construct(
        public string $isoLabel,
        public string $weekStart,
        public float $b2cNet,
        public float $b2bNet,
        public bool $isFuture,
    ) {}
}
