<?php

namespace App\Services\Admin;

use App\Console\Commands\ChargeSubscriptions;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Aggregates admin dashboard KPIs using counts and bounded queries (no N+1 list pages).
 *
 * Upcoming billable subscriptions: Active, non-empty payment token, next_charge_date set,
 * date in window, and not paused on that charge date (aligned with {@see ChargeSubscriptions} intent).
 */
final class AdminDashboardMetrics
{
    public function __construct(
        private readonly UpcomingBillableSubscriptions $upcomingBillable
    ) {}

    public function build(?Carbon $now = null): AdminDashboardMetricsSnapshot
    {
        $now = $now ?? Carbon::now();
        $today = $now->copy()->startOfDay();
        $sevenDaysAgo = $today->copy()->subDays(7);

        $usersTotal = (int) User::query()->count();
        $usersVerified = (int) User::query()->whereNotNull('email_verified_at')->count();
        $usersSignedUpLast7Days = (int) User::query()->where('created_at', '>=', $sevenDaysAgo)->count();

        $statusCounts = Subscription::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $subscriptionsActive = (int) ($statusCounts[SubscriptionStatus::Active->value] ?? 0);
        $subscriptionsPaused = (int) ($statusCounts[SubscriptionStatus::Paused->value] ?? 0);
        $subscriptionsCancelled = (int) ($statusCounts[SubscriptionStatus::Cancelled->value] ?? 0);

        $producersTotal = (int) Producer::query()->count();
        $productsPublished = (int) Product::query()->where('is_published', true)->count();
        $productsDraft = (int) Product::query()->where('is_published', false)->count();

        $paymentsFailedLast7Days = (int) Payment::query()
            ->where('status', Payment::STATUS_FAILED)
            ->where('failed_at', '>=', $sevenDaysAgo)
            ->count();

        $paymentsPending = (int) Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->count();

        $end7 = $today->copy()->addDays(7);
        $end30 = $today->copy()->addDays(30);

        $candidates30 = $this->upcomingBillable->billableBetween($today, $end30);

        $in7 = $candidates30->filter(function (Subscription $s) use ($today, $end7): bool {
            $d = $s->next_charge_date;
            if ($d === null) {
                return false;
            }

            return $d->greaterThanOrEqualTo($today) && $d->lessThanOrEqualTo($end7);
        })->values();

        $amount7 = $in7->sum(fn (Subscription $s): float => $s->expectedChargeAmount());
        $amount30 = $candidates30->sum(fn (Subscription $s): float => $s->expectedChargeAmount());

        $thirtyDaysAgo = $today->copy()->subDays(30);

        $trialOrdersPendingScheduling = (int) Order::query()
            ->where('status', OrderStatus::Paid->value)
            ->whereNull('scheduled_for_date')
            ->count();

        $trialOrdersBoxesToCompose = (int) Order::query()
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Scheduled->value])
            ->whereNull('box_id')
            ->count();

        $trialOrdersDeliveredLast30d = (int) Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->where('delivered_at', '>=', $thirtyDaysAgo)
            ->count();

        $trialOrdersConvertedLast30d = (int) Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->where('delivered_at', '>=', $thirtyDaysAgo)
            ->whereHas('user.subscriptions', function ($q) {
                $q->where('status', SubscriptionStatus::Active->value);
            })
            ->count();

        $trialConversionRate30d = $trialOrdersDeliveredLast30d > 0
            ? round($trialOrdersConvertedLast30d / $trialOrdersDeliveredLast30d, 4)
            : 0.0;

        return new AdminDashboardMetricsSnapshot(
            usersTotal: $usersTotal,
            usersVerified: $usersVerified,
            usersSignedUpLast7Days: $usersSignedUpLast7Days,
            subscriptionsActive: $subscriptionsActive,
            subscriptionsPaused: $subscriptionsPaused,
            subscriptionsCancelled: $subscriptionsCancelled,
            producersTotal: $producersTotal,
            productsPublished: $productsPublished,
            productsDraft: $productsDraft,
            paymentsFailedLast7Days: $paymentsFailedLast7Days,
            paymentsPending: $paymentsPending,
            upcomingBillableCharges7dCount: $in7->count(),
            upcomingBillableCharges30dCount: $candidates30->count(),
            upcomingBillableAmount7d: round($amount7, 2),
            upcomingBillableAmount30d: round($amount30, 2),
            trialOrdersPendingScheduling: $trialOrdersPendingScheduling,
            trialOrdersBoxesToCompose: $trialOrdersBoxesToCompose,
            trialOrdersDeliveredLast30d: $trialOrdersDeliveredLast30d,
            trialOrdersConvertedLast30d: $trialOrdersConvertedLast30d,
            trialConversionRate30d: $trialConversionRate30d,
        );
    }
}
