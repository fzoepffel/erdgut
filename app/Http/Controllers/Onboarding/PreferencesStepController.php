<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Requests\Onboarding\PreferencesStepRequest;
use App\Services\Billing\BoxPriceCalculator;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferencesStepController extends OnboardingController
{
    public function show(Request $request, BoxPriceCalculator $prices): RedirectResponse|View
    {
        if ($request->user() === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);

        // W2-16 item 3: feed every size card the same calculator everyone
        // else uses, so EUR 20 / 35 / 42 stays in lockstep with the
        // recurring charge the customer eventually sees on /payment.
        $sizePrices = [];
        foreach (['small', 'medium', 'large'] as $size) {
            $sizePrices[$size] = $prices->subscriptionPerDelivery($size);
        }

        return view('onboarding.preferences', [
            'draft' => $draft,
            'data' => $draft->data ?? [],
            'sizePrices' => $sizePrices,
            'eggsAddOnEur' => (float) config('box.eggs_dairy_add_on_eur', 4.5),
            'isTrial' => $draft->intent === OnboardingDraftManager::INTENT_TRIAL,
        ]);
    }

    public function store(PreferencesStepRequest $request): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->route('auth.login');
        }

        $draft = $this->drafts->currentOrCreate($request);
        $validated = $request->validated();

        // W2-04 / W2-7: when the customer already declared their intent
        // (e.g. arrived via the marketing 'Subscribe' CTA — `?intent=subscription`),
        // the commitment step's 'Try once vs Subscribe' is redundant. Defer
        // routing to OnboardingDraftManager::nextStep() which already skips
        // commitment when intent is set.
        $this->drafts->update($draft, [
            'box_size' => $validated['box_size'],
            'preferences' => $validated['preferences'],
            'allergies' => $validated['allergies'] ?? [],
            'preferences_completed' => true,
        ]);

        $next = $this->drafts->nextStep($draft->fresh(), $request->user());

        return redirect()->route('app.onboarding.step.'.$next);
    }
}
