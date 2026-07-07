<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        // Restaurant (B2B) accounts never verify their email themselves — the
        // admin review is the gate. Send them to their own status page instead
        // of the box-signup verification prompt (also covers accounts created
        // before restaurant signups auto-verified the email).
        if ($request->user()->isRestaurant()) {
            return redirect()->route(
                $request->user()->isVerifiedRestaurant() ? 'app.restaurant.dashboard' : 'app.restaurant.pending'
            );
        }

        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('app.dashboard', absolute: false))
                    : view('auth.verify-email');
    }
}
