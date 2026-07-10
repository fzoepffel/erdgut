<?php

namespace App\Services\Admin;

/** Immutable snapshot of the operations dashboard numbers. */
final readonly class AdminOperationsSnapshot
{
    /**
     * @param  list<AdminOpsWeekBucket>  $weeklyRevenue  16 buckets, oldest first
     * @param  list<AdminOpsTopProduct>  $topProducts  max 8, by net revenue desc
     */
    public function __construct(
        public array $weeklyRevenue,
        public array $topProducts,
        public float $revenueThisWeekNet,
        public float $revenueThisWeekGross,
        public ?float $revenueWeekDeltaPct,
        public int $ordersThisWeekB2c,
        public int $ordersThisWeekB2b,
        public int $openB2bRequests,
        public int $unpaidB2bInvoicesCount,
        public float $unpaidB2bInvoicesNet,
        public float $outstandingInPersonEur,
        public int $activeStandingOrders,
        public int $pendingRestaurantApplications,
        public float $avgOrderValueB2c30dNet,
        public float $avgOrderValueB2b30dNet,
        public ?float $marginThisWeekNet,
        public float $marginCoveragePct,
    ) {}
}
