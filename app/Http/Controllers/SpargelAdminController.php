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
     * Refund a paid spargel order. Records the refund locally regardless
     * of provider outcome (so the admin always has a paper trail), and
     * additionally calls SumUp's refund API when the order has a stored
     * `sumup_payment_id`. If the SumUp call fails, the local DB is still
     * updated and the admin sees a "recorded but provider failed" flash.
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

        $spargelOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);

        $providerStatus = null;
        $transactionId = (string) ($spargelOrder->sumup_payment_id ?? '');
        if ($transactionId !== '') {
            try {
                $sumUpService->refund($transactionId, $amount);
                $providerStatus = 'ok';
            } catch (Throwable $e) {
                $providerStatus = 'failed';
                SumUpCheckoutLogger::warning('Spargel order refund: SumUp provider call failed.', [
                    'spargel_order_id' => $spargelOrder->id,
                    'sumup_payment_id' => $transactionId,
                    'exception_class' => $e::class,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                ]);
            }
        } else {
            $providerStatus = 'no_transaction_id';
        }

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
                'provider_call' => $providerStatus,
            ], static fn ($v) => $v !== null),
            actorUserId: $actorId,
        );

        $flashKey = match ($providerStatus) {
            'ok' => 'spargel.admin.flash_refunded',
            'failed' => 'spargel.admin.flash_refunded_provider_failed',
            'no_transaction_id' => 'spargel.admin.flash_refunded_local_only',
            default => 'spargel.admin.flash_refunded',
        };

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __($flashKey, ['id' => $spargelOrder->id]));
    }
}
