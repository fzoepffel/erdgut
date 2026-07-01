<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantVerificationStatus;
use App\Http\Controllers\Controller;
use App\Mail\RestaurantApprovedMail;
use App\Mail\RestaurantRejectedMail;
use App\Models\RestaurantProfile;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Admin verify/reject of a restaurant application, reached via a signed link
 * in the admin notification email (no login required — "just click"). The
 * `admin` query param (inside the signature) records who reviewed it.
 */
class RestaurantVerificationController extends Controller
{
    public function review(Request $request, RestaurantProfile $restaurantProfile): View
    {
        $adminId = $request->integer('admin') ?: null;
        $params = ['restaurantProfile' => $restaurantProfile->id, 'admin' => $adminId];

        return view('restaurant.verification-review', [
            'profile' => $restaurantProfile->load('user'),
            'adminId' => $adminId,
            'verifyUrl' => URL::signedRoute('restaurant.verify.approve', $params),
            'rejectUrl' => URL::signedRoute('restaurant.verify.reject', $params),
        ]);
    }

    public function approve(Request $request, RestaurantProfile $restaurantProfile): RedirectResponse
    {
        if (! $restaurantProfile->isPending()) {
            return redirect()->route('restaurant.verify.done', ['result' => 'already']);
        }

        DB::transaction(function () use ($request, $restaurantProfile): void {
            $restaurantProfile->forceFill([
                'verification_status' => RestaurantVerificationStatus::Verified,
                'verified_at' => now(),
                'verified_by_user_id' => $request->integer('admin') ?: null,
            ])->save();

            // Admin approval doubles as email confirmation — don't leave the
            // account stuck behind the unverified gate.
            $user = $restaurantProfile->user;
            if ($user !== null && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        });

        AuditLogger::log(
            entityType: 'restaurant_profile',
            entityId: (int) $restaurantProfile->id,
            action: 'verify',
            fromState: RestaurantVerificationStatus::Pending->value,
            toState: RestaurantVerificationStatus::Verified->value,
            metadata: ['channel' => 'email_link'],
            actorUserId: $request->integer('admin') ?: null,
        );

        // Failure-tolerant: staging runs the sync queue, so a mail/SMTP error
        // must not 500 the approval — the account is already verified above.
        if ($restaurantProfile->user !== null) {
            try {
                Mail::to($restaurantProfile->user->email)
                    ->locale($restaurantProfile->user->preferredLocale())
                    ->send(new RestaurantApprovedMail($restaurantProfile));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('restaurant.verify.done', ['result' => 'verified']);
    }

    public function reject(Request $request, RestaurantProfile $restaurantProfile): RedirectResponse
    {
        $user = $restaurantProfile->user;
        $businessName = $restaurantProfile->business_name;
        $email = $user?->email;
        $locale = $user?->preferredLocale();
        $reason = (string) $request->string('reason')->trim() ?: null;

        AuditLogger::log(
            entityType: 'restaurant_profile',
            entityId: (int) $restaurantProfile->id,
            action: 'reject',
            fromState: $restaurantProfile->verification_status->value,
            toState: RestaurantVerificationStatus::Rejected->value,
            reason: $reason,
            metadata: ['channel' => 'email_link'],
            actorUserId: $request->integer('admin') ?: null,
        );

        // Reject = delete the application (profile cascades with the user).
        $user?->delete();

        // Failure-tolerant (see approve()): the account is already deleted above.
        if ($email !== null) {
            try {
                Mail::to($email)->locale($locale)->send(new RestaurantRejectedMail($businessName, $reason));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('restaurant.verify.done', ['result' => 'rejected']);
    }

    public function done(Request $request): View
    {
        $result = (string) $request->string('result');
        if (! in_array($result, ['verified', 'rejected', 'already'], true)) {
            $result = 'already';
        }

        return view('restaurant.verification-done', ['result' => $result]);
    }
}
