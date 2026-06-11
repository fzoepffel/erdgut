<?php

namespace App\Enums;

use App\Observers\ShopOrderObserver;

/**
 * Where a shop order originated.
 *
 * - Storefront: customer placed the order themselves via the public shop.
 * - Admin: a staff member entered the order manually after a direct
 *   conversation (phone, email, walk-in). No automated emails are sent for
 *   admin-sourced orders; see {@see ShopOrderObserver}.
 */
enum OrderSource: string
{
    case Storefront = 'storefront';
    case Admin = 'admin';
}
