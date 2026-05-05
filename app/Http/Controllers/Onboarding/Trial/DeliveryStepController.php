<?php

namespace App\Http\Controllers\Onboarding\Trial;

use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Requests\Onboarding\TrialDeliveryStepRequest;
use App\Models\Delivery;
use App\Services\Onboarding\OnboardingDraftManager;
use Carbon\CarbonImmutable;
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

        // Placeholder example date: a plausible "after X" hint for the
        // free-text Wunschwoche field. We pick today + 5 days so the
        // suggestion always sits inside the 14-day scheduling promise
        // and never reads as a date in the past after the file ships.
        $exampleDate = CarbonImmutable::today()
            ->locale(app()->getLocale())
            ->addDays(5)
            ->isoFormat('D. MMMM');

        return view('onboarding.trial.delivery', [
            'draft' => $draft,
            'data' => $draft->data ?? [],
            'timeslots' => Delivery::allowedTimeslots(),
            'promiseDays' => (int) config('box.trial_scheduling_promise_days', 14),
            'exampleDate' => $exampleDate,
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
