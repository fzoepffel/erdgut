<?php

namespace App\Enums;

/**
 * Lifecycle for an anonymous shop order.
 *
 * Mirrors {@see SpargelOrderStatus} — the shop generalizes the spargel
 * trial-shop from one product to many but keeps the same small lifecycle:
 * a guest checkout with a one-time SumUp charge, no scheduling/composition
 * machinery. Kept in its own enum so it never picks up state transitions
 * meant for the subscription-bound {@see OrderStatus}.
 */
enum ShopOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
