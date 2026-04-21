<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Requests\Onboarding\PreferencesStepRequest;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferencesStepController extends OnboardingController
{
    public function show(Request $request): RedirectResponse|View
    {
        if ($request->user() === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);

        return view('onboarding.preferences', [
            'draft' => $draft,
            'data' => $draft->data ?? [],
        ]);
    }

    public function store(PreferencesStepRequest $request): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->route('auth.login');
        }

        $draft = $this->drafts->currentOrCreate($request);
        $validated = $request->validated();

        $this->drafts->update($draft, [
            'box_size' => $validated['box_size'],
            'preferences' => $validated['preferences'],
            'allergies' => $validated['allergies'] ?? [],
            'preferences_completed' => true,
        ], OnboardingDraftManager::STEP_COMMITMENT);

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_COMMITMENT);
    }
}
