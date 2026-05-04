<?php

namespace App\Http\Middleware;

use App\Services\Onboarding\OnboardingDraftManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onboarding step-gating: blocks forward URL jumps so a customer can't skip
 * straight to /onboard/subscription/payment by typing the URL. Going back to
 * an earlier step (Edit links, browser back) stays allowed.
 *
 * Discovered during real-SumUp E2E walk 2026-05-04: a customer reached the
 * payment step with no address persisted because the wizard had no per-step
 * gate. The OnboardingDraftManager already knows which step a draft should
 * be on (`nextStep()`); this middleware enforces it on GET requests.
 */
class RedirectToCorrectOnboardingStep
{
    public function __construct(private readonly OnboardingDraftManager $drafts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            // Pre-account step (no auth yet) is fine — the controller handles it.
            return $next($request);
        }

        // Only gate GET requests on `step.*` show-routes; POST/.store handlers
        // already redirect via OnboardingDraftManager::nextStep().
        $route = $request->route();
        $routeName = $route ? (string) $route->getName() : '';
        if (! str_starts_with($routeName, 'app.onboarding.step.') || str_ends_with($routeName, '.store')) {
            return $next($request);
        }

        $draft = $this->drafts->findCurrent($request);
        if ($draft === null) {
            // EnsureOnboardingDraft handles the missing-draft case; if it
            // wasn't applied upstream, fall through and let the controller
            // surface the error rather than masking it here.
            return $next($request);
        }

        $requestedStep = substr($routeName, strlen('app.onboarding.step.'));
        $currentStep = $this->drafts->nextStep($draft, $user);

        $path = $this->pathForIntent((string) $draft->intent);
        $requestedIdx = array_search($requestedStep, $path, true);
        $currentIdx = array_search($currentStep, $path, true);

        // Wrong path entirely (e.g. /onboard/trial/delivery when intent=subscription),
        // or the user is jumping past steps they haven't completed yet.
        if ($requestedIdx === false || ($currentIdx !== false && $requestedIdx > $currentIdx)) {
            return redirect()->route('app.onboarding.step.'.$currentStep);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function pathForIntent(string $intent): array
    {
        return match ($intent) {
            OnboardingDraftManager::INTENT_SUBSCRIPTION => [
                OnboardingDraftManager::STEP_ACCOUNT,
                OnboardingDraftManager::STEP_ADDRESS,
                OnboardingDraftManager::STEP_PREFERENCES,
                OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE,
                OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT,
            ],
            OnboardingDraftManager::INTENT_TRIAL => [
                OnboardingDraftManager::STEP_ACCOUNT,
                OnboardingDraftManager::STEP_ADDRESS,
                OnboardingDraftManager::STEP_PREFERENCES,
                OnboardingDraftManager::STEP_TRIAL_DELIVERY,
                OnboardingDraftManager::STEP_TRIAL_PAYMENT,
            ],
            // Undecided customers route through commitment to pick a path;
            // they can't reach the schedule/payment steps until they choose.
            default => [
                OnboardingDraftManager::STEP_ACCOUNT,
                OnboardingDraftManager::STEP_ADDRESS,
                OnboardingDraftManager::STEP_PREFERENCES,
                OnboardingDraftManager::STEP_COMMITMENT,
            ],
        };
    }
}
