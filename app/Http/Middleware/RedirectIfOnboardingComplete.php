<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The wizard is single-shot once a customer has a *finalised* subscription
 * (active or paused): /onboard/* should not re-create or duplicate it. Those
 * users are bounced — verified ones to the dashboard, unverified ones back to
 * the verify-email page.
 *
 * PendingPayment is the exception. That status means the customer picked a box
 * but never completed the card setup, and the dashboard's "Resume payment
 * setup" CTA sends them straight back into /onboard/subscription/payment to
 * finish. Bouncing *verified* pending-payment users here created an infinite
 * redirect to the dashboard (the wizard could never be reached), so they must
 * be allowed back in. Unverified pending-payment users still have to verify
 * their email before continuing, so they are sent to the verify-email page.
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
                SubscriptionStatus::Paused->value,
            ])
            ->exists();

        // Finalised subscription → wizard is closed.
        if ($hasFinalisedSubscription) {
            return $user->hasVerifiedEmail()
                ? redirect()->route('app.dashboard')
                : redirect()->route('verification.notice');
        }

        // Verified pending-payment users are allowed back into the wizard to
        // finish paying. Unverified users (pending-payment or otherwise) must
        // verify their email first.
        if (! $user->hasVerifiedEmail()) {
            $hasPendingPayment = $user->subscriptions()
                ->where('status', SubscriptionStatus::PendingPayment->value)
                ->exists();

            if ($hasPendingPayment) {
                return redirect()->route('verification.notice');
            }
        }

        return $next($request);
    }
}
