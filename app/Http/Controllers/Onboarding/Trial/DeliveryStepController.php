<?php

namespace App\Http\Controllers\Onboarding\Trial;

use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Requests\Onboarding\TrialDeliveryStepRequest;
use App\Models\Delivery;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeliveryStepController extends OnboardingController
{
    public function show(Request $request): RedirectResponse|View
    {
        if ($request->user() === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);

        return view('onboarding.trial.delivery', [
            'draft' => $draft,
            'data' => $draft->data ?? [],
            'timeslots' => Delivery::allowedTimeslots(),
            'promiseDays' => (int) config('box.trial_scheduling_promise_days', 14),
        ]);
    }

    public function store(TrialDeliveryStepRequest $request): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->route('auth.login');
        }

        $draft = $this->drafts->currentOrCreate($request);
        $validated = $request->validated();

        $this->drafts->update($draft, [
            'preferred_timeslot' => $validated['preferred_timeslot'],
            'preferred_delivery_window' => $validated['preferred_delivery_window'] ?? null,
            'special_instructions' => $validated['special_instructions'] ?? null,
            'trial_delivery_completed' => true,
        ], OnboardingDraftManager::STEP_TRIAL_PAYMENT);

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_TRIAL_PAYMENT);
    }
}
