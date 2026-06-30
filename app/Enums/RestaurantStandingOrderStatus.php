<?php

namespace App\Enums;

/**
 * Lifecycle of a restaurant standing order (recurring weekly delivery).
 *
 * Pending   — created from the first order, awaiting admin confirmation.
 * Active     — the admin confirmed the first order; generates auto-confirmed
 *              weekly orders.
 * Paused     — restaurant paused; generates nothing until resumed.
 * Cancelled — ended; generates nothing.
 */
enum RestaurantStandingOrderStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
}
