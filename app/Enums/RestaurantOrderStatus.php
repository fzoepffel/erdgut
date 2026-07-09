<?php

namespace App\Enums;

/**
 * Lifecycle of a restaurant (B2B) order/request.
 *
 * R4a creates {@see self::Submitted} and lets the restaurant {@see self::Cancelled}.
 * Confirmed / Fulfilled / Declined are driven by the admin surface (R4b).
 *
 * Reservation predicate (see InventoryService): a line reserves stock while its
 * order status is NOT in [Cancelled, Declined].
 */
enum RestaurantOrderStatus: string
{
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Fulfilled = 'fulfilled';
    case Declined = 'declined';

    /** Statuses whose reserved lines still hold stock. */
    public static function reservingValues(): array
    {
        return [
            self::Submitted->value,
            self::Confirmed->value,
            self::Fulfilled->value,
        ];
    }
}
