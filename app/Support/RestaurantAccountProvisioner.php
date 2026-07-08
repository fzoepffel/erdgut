<?php

namespace App\Support;

use App\Enums\RestaurantVerificationStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates (or completes) a verified restaurant account when an admin acts on
 * behalf of a customer WITHOUT an account: invoices go to the email, and the
 * customer can claim the portal later via password reset. Used by the manual
 * shop-order form (recurring deliveries) and the admin request form.
 */
class RestaurantAccountProvisioner
{
    /**
     * @param  array{street: string, postal_code: string, city: string}  $billing
     */
    public function findOrCreateVerified(string $name, string $email, array $billing, int $adminUserId): User
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                // Random unusable password — the customer claims the account
                // via "Passwort vergessen" if they ever want portal access.
                'password' => bcrypt(Str::random(40)),
            ]);
            // Not mass-assignable: the admin vouches for the contact.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $profile = $user->restaurantProfile;
        if ($profile === null) {
            $user->restaurantProfile()->create([
                'business_name' => $name,
                'contact_name' => $name,
                'billing_street' => $billing['street'],
                'billing_postal_code' => $billing['postal_code'],
                'billing_city' => $billing['city'],
                'verification_status' => RestaurantVerificationStatus::Verified,
                'verified_at' => now(),
                'verified_by_user_id' => $adminUserId,
            ]);
        } elseif (! $profile->isVerified()) {
            // The admin is acting for them — that IS the vetting.
            $profile->forceFill([
                'verification_status' => RestaurantVerificationStatus::Verified,
                'verified_at' => now(),
                'verified_by_user_id' => $adminUserId,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();
        }

        return $user;
    }
}
