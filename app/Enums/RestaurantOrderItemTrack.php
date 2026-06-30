<?php

namespace App\Enums;

/**
 * Two-track model for a restaurant order line:
 * - Reserved:  the quantity was in stock at submit and is hard-reserved
 *              (counted by InventoryService, reducing availability for everyone).
 * - Requested: out of stock at submit; recorded as a request that reserves
 *              nothing until an admin sources/confirms it (R4b).
 */
enum RestaurantOrderItemTrack: string
{
    case Reserved = 'reserved';
    case Requested = 'requested';
}
