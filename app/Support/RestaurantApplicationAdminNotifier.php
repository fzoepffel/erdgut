<?php

namespace App\Support;

use App\Mail\RestaurantApplicationAdminNotification;
use App\Models\RestaurantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Emails every admin a "new restaurant application" notice with a signed
 * review link. Mirrors {@see ShopOrderAdminNotifier}: one envelope per admin,
 * deferred to terminating() in HTTP so the applicant's POST never blocks on
 * SMTP, run inline in CLI/tests, failures logged and swallowed.
 *
 * Each admin's link encodes that admin's id (inside the signature) so the
 * verify action can record who approved the account.
 */
class RestaurantApplicationAdminNotifier
{
    public static function applied(RestaurantProfile $profile): void
    {
        $admins = User::query()->where('is_admin', true)->get(['id', 'email']);

        if ($admins->isEmpty()) {
            return;
        }

        $send = function () use ($profile, $admins): void {
            foreach ($admins as $admin) {
                $reviewUrl = URL::signedRoute('restaurant.verify.review', [
                    'restaurantProfile' => $profile->id,
                    'admin' => $admin->id,
                ]);

                try {
                    Mail::to($admin->email)->send(
                        new RestaurantApplicationAdminNotification($profile, $reviewUrl),
                    );
                } catch (Throwable $e) {
                    Log::warning('restaurant.application.admin_notify_failed', [
                        'restaurant_profile_id' => $profile->id,
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
