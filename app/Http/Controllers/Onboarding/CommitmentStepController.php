<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Requests\Onboarding\CommitmentStepRequest;
use App\Services\Billing\BoxPriceCalculator;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommitmentStepController extends OnboardingController
{
    public function show(Request $request, BoxPriceCalculator $prices): RedirectResponse|View
    {
        if ($request->user() === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);
        $data = $draft->data ?? [];

        // W2-04 / W2-7: if the customer already declared an intent (via the
        // marketing 'Subscribe' / 'Try once' CTA), the commit step is
        // redundant — skip to whatever the draft considers next. Direct
        // visits to /onboard/commitment after intent is set get bounced
        // forward instead of asking the question again.
        if (in_array($draft->intent, [OnboardingDraftManager::INTENT_TRIAL, OnboardingDraftManager::INTENT_SUBSCRIPTION], true)) {
            return $this->redirectToNextStep($draft, $request);
        }

        // W2-16 item 3: subscription card subtitle is now per-size + eggs,
        // computed once from the calculator. The previous hardcoded
        // "from EUR 20" lied about every non-Klein subscription.
        $size = is_string($data['box_size'] ?? null) ? (string) $data['box_size'] : 'small';
        $eggsAddOn = (bool) data_get($data, 'preferences.eggs_dairy_add_on', false);
        $subscriptionPerDelivery = $prices->subscriptionPerDelivery($size, $eggsAddOn);

        return view('onboarding.commitment', [
            'draft' => $draft,
            'data' => $data,
            'trialEnabled' => (bool) config('features.trial_orders_enabled', true),
            'trialPrice' => $prices->trialOneOff($size, $eggsAddOn),
            'subscriptionPerDelivery' => $subscriptionPerDelivery,
            'subscriptionSize' => $size,
            'subscriptionEggsAddOn' => $eggsAddOn,
        ]);
    }

    public function store(CommitmentStepRequest $request): RedirectResponse
    {
        $draft = $this->drafts->currentOrCreate($request);
        $intent = $request->validated('intent');

        if ($intent === OnboardingDraftManager::INTENT_TRIAL && ! config('features.trial_orders_enabled', true)) {
            $intent = OnboardingDraftManager::INTENT_SUBSCRIPTION;
        }

        $this->drafts->setIntent($draft, $intent);

        $nextStep = $intent === OnboardingDraftManager::INTENT_TRIAL
            ? OnboardingDraftManager::STEP_TRIAL_DELIVERY
            : OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE;

        $this->drafts->update($draft, ['commitment_completed' => true], $nextStep);

        return redirect()->route('app.onboarding.step.'.$nextStep);
    }
}
