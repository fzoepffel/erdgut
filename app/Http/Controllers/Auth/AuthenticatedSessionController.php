<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, OnboardingDraftManager $drafts): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // B3 (UX notes): if the user logged out mid-onboarding (active
        // draft, no finalised subscription yet) and is unverified, the
        // default `app.dashboard` target bounces them off the verified
        // gate. Send them straight back into the wizard instead — the
        // wizard's start handler picks up the draft and routes to the
        // correct step.
        if (
            $user !== null
            && ! $user->hasVerifiedEmail()
            && $drafts->userHasInFlightDraft($user)
            && ! $user->subscriptions()
                ->whereIn('status', [
                    SubscriptionStatus::Active->value,
                    SubscriptionStatus::PendingPayment->value,
                    SubscriptionStatus::Paused->value,
                ])
                ->exists()
        ) {
            return redirect()->route('app.onboarding.start');
        }

        return redirect()->intended(route('app.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('public.home');
    }
}
