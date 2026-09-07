<?php

namespace App\Services\Admin;

use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a user account, including any restaurant order history they own.
 *
 * The users row is guarded by two restrictOnDelete foreign keys
 * (restaurant_orders.user_id and restaurant_standing_orders.user_id), so those
 * records must be removed before the user itself. Their line items
 * (restaurant_order_items, restaurant_standing_order_items) cascade at the
 * database level, and shop_orders that point at a removed restaurant order have
 * their restaurant_order_id nulled. Everything else on the user (restaurant
 * profile, addresses, subscriptions, payment mandates, deliveries, boxes)
 * cascades or nullifies on the user delete itself.
 *
 * This is a hard delete of order history — used deliberately by the admin
 * "delete user" action. Callers own the authorization and safety checks
 * (cannot delete self, cannot delete the last admin).
 */
class UserDeletionService
{
    public function forceDelete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            RestaurantOrder::query()->where('user_id', $user->id)->delete();
            RestaurantStandingOrder::query()->where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
