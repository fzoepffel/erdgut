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
 * {@see RestaurantApplicationAdminNotifier}: one envelope per admin, deferred to
 * terminating() in HTTP so the restaurant's POST never blocks on SMTP, run
 * inline in CLI/tests, failures logged and swallowed (staging runs the sync
 * queue, so a mail error must not 500 the submission).
 */
class RestaurantOrderAdminNotifier
{
    public static function submitted(RestaurantOrder $order): void
    {
        $admins = User::query()->where('is_admin', true)->get(['id', 'email']);

        if ($admins->isEmpty()) {
            return;
        }

        $send = function () use ($order, $admins): void {
            foreach ($admins as $admin) {
                try {
                    Mail::to($admin->email)->send(new RestaurantOrderAdminNotification($order));
                } catch (Throwable $e) {
                    Log::warning('restaurant.order.admin_notify_failed', [
                        'restaurant_order_id' => $order->id,
                        'recipient' => $admin->email,
                        'exception_class' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        };

        if (app()->runningInConsole()) {
            $send();
        } else {
            app()->terminating($send);
        }
    }
}
