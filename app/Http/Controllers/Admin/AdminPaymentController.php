<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPaymentBulkMarkPaidExternallyRequest;
use App\Http\Requests\Admin\AdminPaymentMarkPaidExternallyRequest;
use App\Http\Requests\Admin\AdminPaymentRefundRequest;
use App\Http\Requests\Admin\AdminPaymentsIndexRequest;
use App\Models\AuditEvent;
use App\Models\Payment;
use App\Support\AuditLogger;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminPaymentController extends Controller
{
    public function index(AdminPaymentsIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);
        $dateField = $validated['date_field'] ?? 'created_at';
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $kind = $validated['kind'] ?? null;

        $query = $this->buildFilteredQuery($validated, $dateField, $sort, $direction);

        $payments = $query->paginate($perPage)->withQueryString();

        return view('admin.operations.payments.index', [
            'payments' => $payments,
            'dateField' => $dateField,
            'kind' => $kind,
        ]);
    }

    /**
     * Stream the filtered payments list as a CSV. Reuses the index filter
     * inputs so admins can export exactly what they're looking at.
     */
    public function export(AdminPaymentsIndexRequest $request): StreamedResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $dateField = $validated['date_field'] ?? 'created_at';
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        $query = $this->buildFilteredQuery($validated, $dateField, $sort, $direction);

        $filename = 'payments-'.Carbon::now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 cleanly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id',
                'type',
                'subscription_id',
                'order_id',
                'customer_email',
                'amount',
                'currency',
                'status',
                'attempt_number',
                'failure_reason',
                'created_at',
                'paid_at',
                'failed_at',
            ]);

            $query->chunkById(500, function ($rows) use ($out): void {
                foreach ($rows as $payment) {
                    $type = $payment->subscription_id ? 'subscription' : ($payment->order_id ? 'order' : '');
                    $email = $payment->subscription?->user?->email
                        ?? $payment->order?->user?->email
                        ?? '';
                    fputcsv($out, [
                        $payment->id,
                        $type,
                        $payment->subscription_id,
                        $payment->order_id,
                        $email,
                        $payment->amount,
                        $payment->currency,
                        $payment->status,
                        $payment->attempt_number,
                        $payment->failure_reason ?? '',
                        $payment->created_at?->toIso8601String() ?? '',
                        $payment->paid_at?->toIso8601String() ?? '',
                        $payment->failed_at?->toIso8601String() ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build a Payment query with the index/export filters applied.
     *
     * @param  array<string, mixed>  $validated
     */
    private function buildFilteredQuery(array $validated, string $dateField, string $sort, string $direction): Builder
    {
        $kind = $validated['kind'] ?? null;

        $query = Payment::query()
            ->select([
                'id',
                'subscription_id',
                'order_id',
                'amount',
                'currency',
                'status',
                'attempt_number',
                'failure_reason',
                'paid_at',
                'failed_at',
                'created_at',
            ])
            ->with([
                'subscription:id,user_id,plan,status',
                'subscription.user:id,email',
                'order:id,user_id',
                'order.user:id,email',
            ]);

        if ($kind === 'subscription') {
            $query->whereNotNull('subscription_id');
        } elseif ($kind === 'order') {
            $query->whereNotNull('order_id');
        }

        if (! empty($validated['subscription_id'])) {
            $query->where('subscription_id', (int) $validated['subscription_id']);
        }

        if (! empty($validated['order_id'])) {
            $query->where('order_id', (int) $validated['order_id']);
        }

        if (($validated['stale_pending'] ?? null) === '1') {
            $hours = (int) config('admin.pending_payment_stale_hours', 48);
            $query->where('status', Payment::STATUS_PENDING)
                ->where('created_at', '<', Carbon::now()->subHours($hours));
        } elseif (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate($dateField, '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate($dateField, '<=', $validated['date_to']);
        }

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    /**
     * ERD-338: admin payment detail page with the audit trail for this payment.
     * Also the host page for the refund action (ERD-337).
     */
    public function show(Payment $payment): View
    {
        $this->authorize('admin');

        $payment->load([
            'subscription:id,user_id,plan,status',
            'subscription.user:id,email',
            'order:id,user_id',
            'order.user:id,email',
        ]);

        $auditEvents = AuditEvent::query()
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->with('actor:id,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.operations.payments.show', [
            'payment' => $payment,
            'auditEvents' => $auditEvents,
            'refundApiEnabled' => (bool) config('payments.refund_api_enabled'),
        ]);
    }

    /**
     * Admin refund action — mirrors the spargel-orders pattern (2026-05-04).
     *
     * Try SumUp first (when a `sumup_payment_id` exists and refund-api is
     * enabled); only flip local status to Refunded after the provider
     * confirms. If SumUp rejects we record the failed attempt on the
     * payment so the admin sees "refund failed — retry" instead of a
     * misleading green badge.
     *
     * Externally-paid payments (no `sumup_payment_id`, or refund-api off)
     * skip SumUp and flip directly — that's the admin recording a manual
     * refund (cash / SEPA reversal) for the books.
     */
    public function refund(AdminPaymentRefundRequest $request, Payment $payment, SumUpService $sumUpService): RedirectResponse
    {
        $this->authorize('admin');

        if ($payment->status !== Payment::STATUS_PAID) {
            return redirect()
                ->back()
                ->with('status', 'admin-payment-refund-not-allowed');
        }

        $previousStatus = $payment->status;
        $amount = $request->validated('refund_amount') !== null
            ? (float) $request->validated('refund_amount')
            : (float) $payment->amount;
        $reason = $request->validated('refund_reason');
        $reference = $request->validated('refund_reference');
        $actorId = (int) $request->user()->id;
        $transactionId = (string) ($payment->sumup_payment_id ?? '');
        $refundApiEnabled = (bool) config('payments.refund_api_enabled');

        $shouldCallProvider = $refundApiEnabled && $transactionId !== '';

        if ($shouldCallProvider) {
            try {
                $sumUpService->refund($transactionId, $amount);
            } catch (Throwable $e) {
                $rawError = SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage());
                $payment->recordRefundAttemptFailed($rawError);

                SumUpCheckoutLogger::warning('Payment refund: SumUp provider call failed — status NOT changed.', [
                    'payment_id' => $payment->id,
                    'sumup_payment_id' => $transactionId,
                    'exception_class' => $e::class,
                    'error' => $rawError,
                    'refund_attempts' => (int) $payment->refund_attempts,
                ]);

                AuditLogger::log(
                    entityType: 'payment',
                    entityId: $payment->id,
                    action: 'refund_attempt_failed',
                    fromState: $previousStatus,
                    toState: $previousStatus,
                    reason: $reason,
                    metadata: array_filter([
                        'amount' => (string) $amount,
                        'currency' => $payment->currency,
                        'refund_reference' => $reference,
                        'error' => $rawError,
                        'attempts' => (string) $payment->refund_attempts,
                    ], static fn ($v) => $v !== null),
                    actorUserId: $actorId,
                );

                return redirect()
                    ->route('app.admin.payments.show', $payment)
                    ->with('status', 'admin-payment-refund-provider-failed');
            }
        }

        // SumUp confirmed (or not applicable) → safe to flip status.
        $payment->recordRefund($amount, $actorId, $reason, $reference);

        AuditLogger::log(
            entityType: 'payment',
            entityId: $payment->id,
            action: 'refund',
            fromState: $previousStatus,
            toState: Payment::STATUS_REFUNDED,
            reason: $reason,
            metadata: array_filter([
                'amount' => (string) $amount,
                'currency' => $payment->currency,
                'refund_reference' => $reference,
                'provider_call' => $shouldCallProvider ? 'ok' : null,
            ], static fn ($v) => $v !== null),
            actorUserId: $actorId,
        );

        return redirect()
            ->route('app.admin.payments.show', $payment)
            ->with('status', 'admin-payment-refunded');
    }

    public function markPaidExternally(AdminPaymentMarkPaidExternallyRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('admin');

        if ($payment->status === Payment::STATUS_PAID) {
            return redirect()
                ->back()
                ->with('status', 'admin-payment-already-paid');
        }

        $previousStatus = $payment->status;
        $reference = (string) $request->validated('external_reference');
        $reason = $request->validated('reason');

        $payment->markPaidExternally($reference, (int) $request->user()->id);

        AuditLogger::log(
            entityType: 'payment',
            entityId: $payment->id,
            action: 'manual_paid',
            fromState: $previousStatus,
            toState: Payment::STATUS_PAID,
            reason: $reason,
            metadata: [
                'external_reference' => $reference,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'admin-payment-marked-paid-externally');
    }

    /**
     * Bulk-mark a list of payments as externally paid (e.g. SEPA reconciliation).
     *
     * Skips already-paid / refunded rows silently — admins typically include
     * them by accident from a "select all on page" gesture. Each updated row
     * gets the same external reference + reason so the audit trail can be
     * grouped after the fact via `external_reference`.
     */
    public function bulkMarkPaidExternally(AdminPaymentBulkMarkPaidExternallyRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map('intval', (array) $request->validated('payment_ids'))));
        $reference = (string) $request->validated('external_reference');
        $reason = $request->validated('reason');
        $actorId = (int) $request->user()->id;

        $payments = Payment::query()->whereIn('id', $ids)->get();
        $updated = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            if (in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_REFUNDED], true)) {
                $skipped++;

                continue;
            }

            $previousStatus = $payment->status;
            $payment->markPaidExternally($reference, $actorId);

            AuditLogger::log(
                entityType: 'payment',
                entityId: $payment->id,
                action: 'manual_paid',
                fromState: $previousStatus,
                toState: Payment::STATUS_PAID,
                reason: $reason,
                metadata: [
                    'external_reference' => $reference,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'bulk' => true,
                ],
            );

            $updated++;
        }

        return redirect()
            ->back()
            ->with('status', 'admin-payment-bulk-marked-paid-externally')
            ->with('bulk_marked_count', $updated)
            ->with('bulk_skipped_count', $skipped);
    }
}
