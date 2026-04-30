<?php

namespace App\Enums;

/**
 * Lifecycle for the spargel trial-shop order.
 *
 * Lives in its own enum so it doesn't pick up state changes intended for the
 * main {@see OrderStatus}. The trial shop has a much smaller surface — there
 * is no scheduling, no composition, no admin retry workflow.
 */
enum SpargelOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
