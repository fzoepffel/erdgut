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
 * QUEUED so the applicant's POST never touches SMTP (the cron-driven worker
 * delivers within a minute), failures logged and swallowed.
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

        foreach ($admins as $admin) {
            $reviewUrl = URL::signedRoute('restaurant.verify.review', [
                'restaurantProfile' => $profile->id,
                'admin' => $admin->id,
            ]);

            try {
                Mail::to($admin->email)->queue(
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
    }
}
