<?php

namespace App\Enums;

/**
 * The two B2B artifact kinds. An ORDER comes from the shop: every line was in
 * stock, is hard-reserved, and the order is confirmed the moment it's placed.
 * A REQUEST comes from the Anfragen area: season items, over-stock amounts or
 * free-form wishes — nothing is reserved, the admin accepts/declines per line
 * and prices before invoicing. An artifact is never mixed.
 */
enum RestaurantOrderKind: string
{
    case Order = 'order';
    case Request = 'request';
}
