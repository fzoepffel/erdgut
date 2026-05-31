<?php

namespace App\Observers;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Shop\ShopController;
use App\Models\ShopOrder;
use App\Support\ShopOrderAdminNotifier;

/**
 * Side effects on ShopOrder lifecycle.
 *
 * Handles the *online* half of the "finalized order" admin notification: an
 * online order is a confirmed sale once SumUp payment lands, i.e. when status
 * flips to Paid. At that point the order's line items already exist, so the
 * email renders correctly from a model event.
 *
 * The *in-person* half is fired explicitly by
 * {@see ShopController::store()} after the order
 * transaction commits — its items are inserted after the parent row, so the
 * `created` event fires too early to render the email here.
 *
 * Guarding on `payment_method === 'online'` guarantees exactly one admin email
 * per order: an in-person order later marked Paid by an admin fires this
 * `updated → Paid` transition but is skipped (already notified at checkout),
 * and abandoned online checkouts never reach Paid at all.
 */
class ShopOrderObserver
{
    public function updated(ShopOrder $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = $order->getOriginal('status');
        $previousValue = $previous instanceof ShopOrderStatus ? $previous->value : (string) $previous;
        $currentValue = $order->status instanceof ShopOrderStatus ? $order->status->value : (string) $order->status;

        if ($currentValue !== ShopOrderStatus::Paid->value
            || $previousValue === ShopOrderStatus::Paid->value
            || $order->payment_method !== 'online') {
            return;
        }

        ShopOrderAdminNotifier::finalized($order);
    }
}
