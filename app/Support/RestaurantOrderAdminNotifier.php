<?php

namespace App\Support;

use App\Mail\RestaurantOrderAdminNotification;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails every admin a "new restaurant request" notice. Mirrors
 * {@see RestaurantApplicationAdminNotifier}: one envelope per admin, QUEUED so
 * the restaurant's POST never touches SMTP (the cron-driven worker delivers
 * within a minute), failures logged and swallowed (on the sync queue driver a
 * mail error would otherwise 500 the submission).
 */
class RestaurantOrderAdminNotifier
{
    public static function submitted(RestaurantOrder $order): void
    {
        self::notify($order, adjusted: false);
    }

    /** The customer changed a confirmed delivery — packing lists move. */
    public static function adjusted(RestaurantOrder $order): void
    {
        self::notify($order, adjusted: true);
    }

    private static function notify(RestaurantOrder $order, bool $adjusted): void
    {
        $admins = User::query()->where('is_admin', true)->get(['id', 'email']);

        if ($admins->isEmpty()) {
            return;
        }

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->queue(new RestaurantOrderAdminNotification($order, $adjusted));
            } catch (Throwable $e) {
                Log::warning('restaurant.order.admin_notify_failed', [
                    'restaurant_order_id' => $order->id,
                    'recipient' => $admin->email,
                    'exception_class' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
