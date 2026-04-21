<?php

namespace App\Services\Admin;

/**
 * Read-only KPI snapshot for the admin overview (ERD-185).
 */
final readonly class AdminDashboardMetricsSnapshot
{
    public function __construct(
        public int $usersTotal,
        public int $usersVerified,
        public int $usersSignedUpLast7Days,
        public int $subscriptionsActive,
        public int $subscriptionsPaused,
        public int $subscriptionsCancelled,
        public int $producersTotal,
        public int $productsPublished,
        public int $productsDraft,
        public int $paymentsFailedLast7Days,
        public int $paymentsPending,
        public int $upcomingBillableCharges7dCount,
        public int $upcomingBillableCharges30dCount,
        public float $upcomingBillableAmount7d,
        public float $upcomingBillableAmount30d,
        public int $trialOrdersPendingScheduling = 0,
        public int $trialOrdersBoxesToCompose = 0,
        public int $trialOrdersDeliveredLast30d = 0,
        public int $trialOrdersConvertedLast30d = 0,
        public float $trialConversionRate30d = 0.0,
    ) {}
}
