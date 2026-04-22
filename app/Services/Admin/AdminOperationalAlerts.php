<?php

namespace App\Services\Admin;

use App\Enums\OrderProductType;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * DB-backed operational notices with deep links into admin operations browsers.
 */
final class AdminOperationalAlerts
{
    public function __construct(
        private readonly AdminDashboardMetrics $metrics
    ) {}

    /**
     * @return list<AdminOperationalAlert>
     */
    public function build(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $today = $now->copy()->startOfDay();
        $sevenDaysAgo = $today->copy()->subDays(7);

        $alerts = [];
        $snapshot = $this->metrics->build($now);

        if ($snapshot->paymentsFailedLast7Days > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'warning',
                messageKey: 'admin.alerts.failed_payments_recent',
                messageParams: ['count' => $snapshot->paymentsFailedLast7Days],
                href: route('app.admin.payments.index', [
                    'status' => Payment::STATUS_FAILED,
                    'date_field' => 'failed_at',
                    'date_from' => $sevenDaysAgo->toDateString(),
                    'date_to' => $today->toDateString(),
                ], false),
                linkTextKey: 'admin.alerts.link_payments_failed',
            );
        }

        $staleHours = (int) config('admin.pending_payment_stale_hours', 48);
        $stalePendingCount = (int) Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<', $now->copy()->subHours($staleHours))
            ->count();

        if ($stalePendingCount > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'warning',
                messageKey: 'admin.alerts.pending_stale',
                messageParams: ['count' => $stalePendingCount, 'hours' => $staleHours],
                href: route('app.admin.payments.index', [
                    'status' => Payment::STATUS_PENDING,
                    'stale_pending' => '1',
                ], false),
                linkTextKey: 'admin.alerts.link_payments_pending',
            );
        }

        $activeMissingToken = (int) Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNull('sumup_token')
            ->count();

        if ($activeMissingToken > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'warning',
                messageKey: 'admin.alerts.active_missing_token',
                messageParams: ['count' => $activeMissingToken],
                href: route('app.admin.subscriptions.index', ['missing_payment' => '1'], false),
                linkTextKey: 'admin.alerts.link_subscriptions_missing',
            );
        }

        $maxRetries = (int) config('payments.max_retries', 3);
        $pausedExhausted = (int) Subscription::query()
            ->where('status', SubscriptionStatus::Paused)
            ->whereHas('payments', function ($pq) use ($maxRetries): void {
                $pq->where('status', Payment::STATUS_FAILED)
                    ->where('attempt_number', '>=', $maxRetries);
            })
            ->count();

        if ($pausedExhausted > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'warning',
                messageKey: 'admin.alerts.paused_exhausted',
                messageParams: ['count' => $pausedExhausted],
                href: route('app.admin.subscriptions.index', ['paused_exhausted' => '1'], false),
                linkTextKey: 'admin.alerts.link_subscriptions_paused',
            );
        }

        if ($snapshot->trialOrdersPendingScheduling > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'warning',
                messageKey: 'admin.alerts.trial_orders_pending_scheduling',
                messageParams: ['count' => $snapshot->trialOrdersPendingScheduling],
                href: route('app.admin.orders.index', [
                    'product_type' => OrderProductType::TrialBox->value,
                    'status' => 'paid',
                ], false),
                linkTextKey: 'admin.alerts.link_trial_orders',
            );
        }

        if ($snapshot->trialOrdersBoxesToCompose > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'info',
                messageKey: 'admin.alerts.trial_orders_boxes_to_compose',
                messageParams: ['count' => $snapshot->trialOrdersBoxesToCompose],
                href: route('app.admin.orders.index', [
                    'product_type' => OrderProductType::TrialBox->value,
                    'status' => 'scheduled',
                ], false),
                linkTextKey: 'admin.alerts.link_trial_orders',
            );
        }

        if ($snapshot->productsDraft > 0) {
            $alerts[] = new AdminOperationalAlert(
                severity: 'warning',
                messageKey: 'admin.alerts.draft_products',
                messageParams: ['count' => $snapshot->productsDraft],
                href: route('app.admin.products.index', ['published' => '0'], false),
                linkTextKey: 'admin.alerts.link_products_draft',
            );
        }

        return $alerts;
    }
}
