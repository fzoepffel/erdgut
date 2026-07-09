<?php

namespace App\Enums;

/**
 * Per-line confirmation status on a restaurant (B2B) order.
 *
 * In-stock lines are {@see self::Confirmed} the moment they're submitted (a firm
 * "Bestellung" — reserved + priced). On-request/seasonal + free-form lines start
 * {@see self::Requested} and wait for the admin to accept ({@see self::Confirmed})
 * or {@see self::Declined} them per line. An order is confirmed once none of its
 * lines are still Requested.
 */
enum RestaurantOrderLineStatus: string
{
    case Confirmed = 'confirmed';
    case Requested = 'requested';
    case Declined = 'declined';
}
