<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SpargelAdminController;
use App\Models\ShopOrder;
use App\Services\Shop\CouponService;
use App\Support\AuditLogger;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Proper admin surface for anonymous {@see ShopOrder}s.
 *
 * Unlike the disposable {@see SpargelAdminController}
 * (standalone page, no shell, no audit log), this lives inside the main
 * `app.admin.*` group: shares the sidebar shell, status filtering, and
 * writes every state change to the audit log. Mirrors the spargel admin's
 * action set generalized to multi-product orders.
 *
 *   GET  /app/admin/shop-orders                       list + filter
 *   GET  /app/admin/shop-orders/{shopOrder}           detail
 *   POST /app/admin/shop-orders/{shopOrder}/mark-paid       in-person orders
 *   POST /app/admin/shop-orders/{shopOrder}/mark-completed  paid + fulfilled
 *   POST /app/admin/shop-orders/{shopOrder}/refund          refund a paid order
 */
class AdminShopOrderController extends Controller
{
    /**
     * Default filter: only "finalized" orders — confirmed sales the team
     * actually has to act on. That means paid orders, already-fulfilled
     * orders, and the in-person ones still awaiting payment at pickup
     * ("Zahlung bei Abholung"). Abandoned online checkouts, failed,
     * cancelled and refunded orders are hidden unless explicitly chosen.
     */
    private const FILTER_FINALIZED = 'finalized';

    public function index(Request $request): View
    {
        $statusParam = $request->query('status', self::FILTER_FINALIZED);
        $status = is_string($statusParam) ? $statusParam : self::FILTER_FINALIZED;
        $statusEnum = ShopOrderStatus::tryFrom($status);

        $deliveryDate = (string) $request->query('delivery_date', '');

        $orders = ShopOrder::query()
            ->with('items.product.translations')
            ->when($status === self::FILTER_FINALIZED, fn ($q) => $q->where(function ($inner) {
                $inner->whereIn('status', [
                    ShopOrderStatus::Paid->value,
                    ShopOrderStatus::Fulfilled->value,
                ])->orWhere(function ($pickup) {
                    $pickup->where('status', ShopOrderStatus::PendingPayment->value)
                        ->where('payment_method', 'in_person');
                });
            }))
            ->when($statusEnum, fn ($q) => $q->where('status', $statusEnum))
            ->when($deliveryDate !== '', fn ($q) => $q->whereDate('expected_delivery_date', $deliveryDate))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.shop-orders.index', [
            'orders' => $orders,
            'statuses' => ShopOrderStatus::cases(),
            'status' => $status,
            'deliveryDate' => $deliveryDate,
        ]);
    }

    public function show(ShopOrder $shopOrder): View
    {
        $shopOrder->load('items.product.translations', 'items.product.producer.translations');

        return view('admin.shop-orders.show', [
            'order' => $shopOrder,
        ]);
    }

    public function markPaid(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        if ($shopOrder->status === ShopOrderStatus::PendingPayment) {
            $shopOrder->forceFill([
                'status' => ShopOrderStatus::Paid,
                'paid_at' => now(),
                'marked_paid_by_admin_at' => now(),
            ])->save();

            AuditLogger::log(
                entityType: 'shop_order',
                entityId: $shopOrder->id,
                action: 'mark_paid',
                fromState: ShopOrderStatus::PendingPayment->value,
                toState: ShopOrderStatus::Paid->value,
                reason: null,
                metadata: ['channel' => 'admin'],
                actorUserId: (int) $request->user()->id,
            );
        }

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.flash_marked_paid', ['id' => $shopOrder->id]));
    }

    public function markCompleted(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $now = now();
        $from = $shopOrder->status->value;
        $payload = [
            'status' => ShopOrderStatus::Fulfilled,
            'fulfilled_at' => $now,
        ];

        // In-person order completed in one click: flip paid + fulfilled at once.
        if ($shopOrder->status === ShopOrderStatus::PendingPayment) {
            $payload['paid_at'] = $now;
            $payload['marked_paid_by_admin_at'] = $now;
        }

        $shopOrder->forceFill($payload)->save();

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'mark_completed',
            fromState: $from,
            toState: ShopOrderStatus::Fulfilled->value,
            reason: null,
            metadata: ['channel' => 'admin'],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.flash_marked_completed', ['id' => $shopOrder->id]));
    }

    /**
     * Permanently delete a shop order (cascading to its items). Mirrors
     * {@see SpargelAdminController::destroy()} but writes an audit row first
     * — once the order row is gone, the audit log is its only trace. Deleted
     * orders no longer commit stock, so inventory is released automatically.
     */
    public function destroy(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $id = $shopOrder->id;

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $id,
            action: 'delete',
            fromState: $shopOrder->status->value,
            toState: null,
            reason: null,
            metadata: ['channel' => 'admin'],
            actorUserId: (int) $request->user()->id,
        );

        // Release any finalized coupon slot before the row (and its FK) is
        // gone — voidRedemption looks the redemption up by shop_order_id.
        app(CouponService::class)->voidRedemption($shopOrder);

        $shopOrder->delete();

        return redirect()
            ->route('app.admin.shop-orders.index')
            ->with('status', __('shop.admin.flash_deleted', ['id' => $id]));
    }

    /**
     * Refund a paid shop order. Asks SumUp first — only flip local status to
     * Refunded once SumUp confirms. Externally-paid orders (no transaction id)
     * are recorded as a manual refund without a provider call. Mirrors
     * {@see SpargelAdminController::refund()}.
     */
    public function refund(Request $request, ShopOrder $shopOrder, SumUpService $sumUpService): RedirectResponse
    {
        if ($shopOrder->status !== ShopOrderStatus::Paid) {
            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('status', __('shop.admin.flash_refund_not_allowed', ['id' => $shopOrder->id]));
        }

        $reason = (string) $request->input('refund_reason', '');
        $amount = (float) $shopOrder->total_amount_eur;
        $actorId = (int) $request->user()->id;
        $transactionId = (string) ($shopOrder->sumup_payment_id ?? '');

        // No SumUp transaction id ⇒ externally-paid (in-person) order; record
        // a manual refund without calling SumUp.
        if ($transactionId === '') {
            $shopOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);
            AuditLogger::log(
                entityType: 'shop_order',
                entityId: $shopOrder->id,
                action: 'refund',
                fromState: ShopOrderStatus::Paid->value,
                toState: ShopOrderStatus::Refunded->value,
                reason: $reason !== '' ? $reason : null,
                metadata: array_filter([
                    'amount' => (string) $amount,
                    'currency' => 'EUR',
                    'provider_call' => 'no_transaction_id',
                ], static fn ($v) => $v !== null),
                actorUserId: $actorId,
            );

            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('status', __('shop.admin.flash_refunded_local_only', ['id' => $shopOrder->id]));
        }

        // Standard path: ask SumUp first, only promote status on success.
        try {
            $sumUpService->refund($transactionId, $amount);
        } catch (Throwable $e) {
            $rawError = SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage());
            $shopOrder->recordRefundAttemptFailed($rawError);

            SumUpCheckoutLogger::warning('Shop order refund: SumUp provider call failed — status NOT changed.', [
                'shop_order_id' => $shopOrder->id,
                'sumup_payment_id' => $transactionId,
                'exception_class' => $e::class,
                'error' => $rawError,
                'refund_attempts' => (int) $shopOrder->refund_attempts,
            ]);

            AuditLogger::log(
                entityType: 'shop_order',
                entityId: $shopOrder->id,
                action: 'refund_attempt_failed',
                fromState: ShopOrderStatus::Paid->value,
                toState: ShopOrderStatus::Paid->value,
                reason: $reason !== '' ? $reason : null,
                metadata: array_filter([
                    'amount' => (string) $amount,
                    'currency' => 'EUR',
                    'error' => $rawError,
                    'attempts' => (string) $shopOrder->refund_attempts,
                ], static fn ($v) => $v !== null),
                actorUserId: $actorId,
            );

            return redirect()
                ->route('app.admin.shop-orders.show', $shopOrder)
                ->with('status', __('shop.admin.flash_refund_provider_failed', ['id' => $shopOrder->id]));
        }

        // SumUp confirmed → safe to flip status.
        $shopOrder->recordRefund($amount, $actorId, $reason !== '' ? $reason : null);

        AuditLogger::log(
            entityType: 'shop_order',
            entityId: $shopOrder->id,
            action: 'refund',
            fromState: ShopOrderStatus::Paid->value,
            toState: ShopOrderStatus::Refunded->value,
            reason: $reason !== '' ? $reason : null,
            metadata: array_filter([
                'amount' => (string) $amount,
                'currency' => 'EUR',
                'provider_call' => 'ok',
            ], static fn ($v) => $v !== null),
            actorUserId: $actorId,
        );

        return redirect()
            ->route('app.admin.shop-orders.show', $shopOrder)
            ->with('status', __('shop.admin.flash_refunded', ['id' => $shopOrder->id]));
    }
}
