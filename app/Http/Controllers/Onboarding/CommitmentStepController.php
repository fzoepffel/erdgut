<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Requests\Onboarding\CommitmentStepRequest;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommitmentStepController extends OnboardingController
{
    public function show(Request $request): RedirectResponse|View
    {
        if ($request->user() === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);

        return view('onboarding.commitment', [
            'draft' => $draft,
            'data' => $draft->data ?? [],
            'trialEnabled' => (bool) config('features.trial_orders_enabled', true),
            'trialPrice' => (float) config('box.trial_price_oneoff_ttc', 35),
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
