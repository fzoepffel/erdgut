<?php

namespace App\Support;

use App\Mail\ShopOrderFinalizedAdminNotification;
use App\Models\ShopOrder;
use App\Models\User;
use App\Observers\ShopOrderObserver;
use App\Services\Shop\ShopOrderService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Fans a "finalized order" notification out to every admin.
 *
 * Centralized here because a finalized shop order is reached from two
 * different places that can't share an Eloquent event cleanly:
 *
 *  - in-person orders are confirmed at checkout, but their line items are
 *    inserted *after* the parent row (so the `created` model event fires too
 *    early to render the email) — the controller calls {@see self::finalized()}
 *    once {@see ShopOrderService::createOrder()} has fully
 *    persisted the order;
 *  - online orders are confirmed when SumUp payment lands — the
 *    {@see ShopOrderObserver} calls it on the status → Paid
 *    transition, where the items already exist.
 *
 * Sends are QUEUED so the customer-facing POST never touches SMTP (slow and
 * occasionally hanging on IONOS shared hosting); the cron-driven queue worker
 * delivers within a minute. Failures are logged and swallowed — an admin
 * notification must never break the customer flow.
 */
class ShopOrderAdminNotifier
{
    public static function finalized(ShopOrder $order): void
    {
        $adminEmails = User::query()
            ->where('is_admin', true)
            ->pluck('email')
            ->all();

        if ($adminEmails === []) {
            return;
        }

        // Re-load fresh so the email always reflects committed state including
        // line items (the in-person path notifies right after the order
        // transaction commits).
        $order = ShopOrder::query()
            ->with('items.product.translations')
            ->find($order->id) ?? $order;

        foreach ($adminEmails as $email) {
            try {
                Mail::to($email)->queue(new ShopOrderFinalizedAdminNotification($order));
            } catch (Throwable $e) {
                Log::warning('shop.admin_notification.send_failed', [
                    'shop_order_id' => $order->id,
                    'recipient' => $email,
                    'exception_class' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
