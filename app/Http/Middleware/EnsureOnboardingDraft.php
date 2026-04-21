<?php

namespace App\Http\Middleware;

use App\Services\Onboarding\OnboardingDraftManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards mid-wizard routes: if there is no active onboarding draft for the
 * current user/session, send them back to the wizard start.
 */
class EnsureOnboardingDraft
{
    public function __construct(private readonly OnboardingDraftManager $drafts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $draft = $this->drafts->findCurrent($request);

        if ($draft === null) {
            return redirect()->route('app.onboarding.start');
        }

        $request->attributes->set('onboarding_draft', $draft);

        return $next($request);
    }
}
