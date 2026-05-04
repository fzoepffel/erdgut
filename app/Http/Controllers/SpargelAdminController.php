<?php

namespace App\Http\Controllers;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use App\Support\AuditLogger;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Disposable admin view for the spargel trial-shop.
 *
 * Intentionally NOT plugged into the main admin sidebar / app shell —
 * this is a standalone page with a single hardcoded URL, kept dead
 * simple so we can rip it out when the proper shop launches. No
 * navigation links from elsewhere, no shared layout, no audit log
 * integration. Lives behind the existing `admin` gate.
 *
 *   GET  /spargel-admin                          list all orders
 *   POST /spargel-admin/{order}/mark-paid        for in-person orders
 *   POST /spargel-admin/{order}/mark-completed   = paid + delivered/picked-up
 */
class SpargelAdminController extends Controller
{
    public function index(): View
    {
        $orders = SpargelOrder::query()
            ->orderByDesc('id')
            ->get();

        return view('spargel.admin.index', [
            'orders' => $orders,
        ]);
    }

    public function markPaid(Request $request, SpargelOrder $spargelOrder): RedirectResponse
    {
        if ($spargelOrder->status === SpargelOrderStatus::PendingPayment) {
            $spargelOrder->forceFill([
                'status' => SpargelOrderStatus::Paid,
                'paid_at' => now(),
                'marked_paid_by_admin_at' => now(),
            ])->save();
        }

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __('spargel.admin.flash_marked_paid', ['id' => $spargelOrder->id]));
    }

    public function markCompleted(Request $request, SpargelOrder $spargelOrder): RedirectResponse
    {
        $now = now();
        $payload = ['fulfilled_at' => $now];

        // If the order was an in-person one and admin only now realized
        // that — flip both states at once. Saves a second click.
        if ($spargelOrder->status === SpargelOrderStatus::PendingPayment) {
            $payload['status'] = SpargelOrderStatus::Fulfilled;
            $payload['paid_at'] = $now;
            $payload['marked_paid_by_admin_at'] = $now;
        } else {
            $payload['status'] = SpargelOrderStatus::Fulfilled;
        }

        $spargelOrder->forceFill($payload)->save();

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __('spargel.admin.flash_marked_completed', ['id' => $spargelOrder->id]));
    }

    /**
     * Hard-delete a single order. Built for the early-launch reset where
     * every order on prod so far is a test order. SpargelOrder is
     * standalone (no FK relations) so the delete is straightforward.
     */
    public function destroy(Request $request, SpargelOrder $spargelOrder): RedirectResponse
    {
        $id = $spargelOrder->id;
        $spargelOrder->delete();

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __('spargel.admin.flash_deleted', ['id' => $id]));
    }

    /**
     * Refund a paid spargel order. Tries SumUp's refund API first — only
     * if SumUp confirms the refund do we flip local status to Refunded.
     * If SumUp rejects (NOT_ENOUGH_BALANCE, settlement-pending, etc.) the
     * order stays Paid and we record the failed attempt so the admin sees
     * "refund failed — retry" rather than a misleading "refunded" badge.
     */
    public function refund(Request $request, SpargelOrder $spargelOrder, SumUpService $sumUpService): RedirectResponse
    {
        if ($spargelOrder->status !== SpargelOrderStatus::Paid) {
            return redirect()
                ->route('spargel.admin.index')
                ->with('status', __('spargel.admin.flash_refund_not_allowed', ['id' => $spargelOrder->id]));
        }

        $reason = (string) $request->input('refund_reason', '');
        $amount = (float) $spargelOrder->total_amount_eur;
        $actorId = (int) $request->user()->id;
        $transactionId = (string) ($spargelOrder->sumup_payment_id ?? '');

        // No SumUp transaction id ⇒ this is an externally-paid order
        // (cash, bank transfer) we're recording a manual refund for.
        // Flip to Refunded directly without calling SumUp.
        if ($transactionId === '') {
            $spargelOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);
            AuditLogger::log(
                entityType: 'spargel_order',
                entityId: $spargelOrder->id,
                action: 'refund',
                fromState: SpargelOrderStatus::Paid->value,
                toState: SpargelOrderStatus::Refunded->value,
                reason: $reason !== '' ? $reason : null,
                metadata: array_filter([
                    'amount' => (string) $amount,
                    'currency' => 'EUR',
                    'provider_call' => 'no_transaction_id',
                ], static fn ($v) => $v !== null),
                actorUserId: $actorId,
            );

            return redirect()
                ->route('spargel.admin.index')
                ->with('status', __('spargel.admin.flash_refunded_local_only', ['id' => $spargelOrder->id]));
        }

        // Standard path: ask SumUp first, only promote status on success.
        try {
            $sumUpService->refund($transactionId, $amount);
        } catch (Throwable $e) {
            $rawError = SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage());
            $spargelOrder->recordRefundAttemptFailed($rawError);

            SumUpCheckoutLogger::warning('Spargel order refund: SumUp provider call failed — status NOT changed.', [
                'spargel_order_id' => $spargelOrder->id,
                'sumup_payment_id' => $transactionId,
                'exception_class' => $e::class,
                'error' => $rawError,
                'refund_attempts' => (int) $spargelOrder->refund_attempts,
            ]);

            AuditLogger::log(
                entityType: 'spargel_order',
                entityId: $spargelOrder->id,
                action: 'refund_attempt_failed',
                fromState: SpargelOrderStatus::Paid->value,
                toState: SpargelOrderStatus::Paid->value,
                reason: $reason !== '' ? $reason : null,
                metadata: array_filter([
                    'amount' => (string) $amount,
                    'currency' => 'EUR',
                    'error' => $rawError,
                    'attempts' => (string) $spargelOrder->refund_attempts,
                ], static fn ($v) => $v !== null),
                actorUserId: $actorId,
            );

            return redirect()
                ->route('spargel.admin.index')
                ->with('status', __('spargel.admin.flash_refund_provider_failed', ['id' => $spargelOrder->id]));
        }

        // SumUp confirmed → safe to flip status.
        $spargelOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);

        AuditLogger::log(
            entityType: 'spargel_order',
            entityId: $spargelOrder->id,
            action: 'refund',
            fromState: SpargelOrderStatus::Paid->value,
            toState: SpargelOrderStatus::Refunded->value,
            reason: $reason !== '' ? $reason : null,
            metadata: array_filter([
                'amount' => (string) $amount,
                'currency' => 'EUR',
                'provider_call' => 'ok',
            ], static fn ($v) => $v !== null),
            actorUserId: $actorId,
        );

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __('spargel.admin.flash_refunded', ['id' => $spargelOrder->id]));
    }
}
