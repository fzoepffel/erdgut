<?php

namespace App\Http\Controllers\Onboarding\Subscription;

use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Subscription;
use App\Services\Onboarding\OnboardingDraftManager;
use App\Services\Onboarding\SubscriptionOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentStepController extends OnboardingController
{
    public function __construct(
        OnboardingDraftManager $drafts,
        private readonly SubscriptionOnboardingService $service,
    ) {
        parent::__construct($drafts);
    }

    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);
        $data = $draft->data ?? [];

        $planValue = $data['plan'] ?? SubscriptionPlan::Weekly->value;

        $subscription = $this->service->ensureSubscription($user, [
            'plan' => $planValue,
            'box_size' => $data['box_size'] ?? 'medium',
            'preferences' => $data['preferences'] ?? [],
            'allergies' => $data['allergies'] ?? [],
            'fulfillment_mode' => $data['fulfillment_mode'] ?? null,
            'preferred_timeslot' => $data['preferred_timeslot'] ?? null,
        ]);

        if (! empty($subscription->sumup_token)) {
            return redirect()
                ->route('app.dashboard')
                ->with('status', 'payment-already-setup');
        }

        $checkoutId = $this->service->createSetupCheckoutForSubscription($subscription, $user);

        if ($checkoutId === null) {
            return redirect()
                ->route('app.onboarding.step.'.OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE)
                ->with('status', 'payment-setup-unavailable');
        }

        return view('onboarding.subscription.payment', [
            'draft' => $draft,
            'subscription' => $subscription,
            'checkoutId' => $checkoutId,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('auth.login');
        }

        $subscription = Subscription::billingSubscriptionForUser($user);
        if ($subscription === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE);
        }

        $checkoutId = (string) ($request->query('checkout', '') ?: $request->query('checkout_id', ''));

        if (trim($checkoutId) === '') {
            return redirect()
                ->route('app.onboarding.step.'.OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT)
                ->with('status', 'payment-setup-failed');
        }

        $ok = $this->service->finalizeSetup($subscription, $checkoutId);

        if (! $ok) {
            return redirect()
                ->route('app.onboarding.step.'.OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT)
                ->with('status', 'payment-setup-failed');
        }

        $draft = $this->drafts->findCurrent($request);
        if ($draft !== null) {
            $this->drafts->clear($draft);
        }

        return redirect()
            ->route('app.dashboard')
            ->with('status', 'subscription-onboarded');
    }
}
