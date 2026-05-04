<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The wizard is single-shot: once a customer has an active or pending-payment
 * Subscription, /onboard/* should not be accessible. Without this gate an
 * authenticated-but-unverified user (e.g. just bounced to /auth/email/verify)
 * could re-enter the wizard by typing the URL.
 *
 * Verified users go to the dashboard. Unverified users go back to the
 * verify-email page they were bounced to.
 */
class RedirectIfOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $hasFinalisedSubscription = $user->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PendingPayment->value,
                SubscriptionStatus::Paused->value,
            ])
            ->exists();

        if (! $hasFinalisedSubscription) {
            return $next($request);
        }

        return $user->hasVerifiedEmail()
            ? redirect()->route('app.dashboard')
            : redirect()->route('verification.notice');
    }
}
