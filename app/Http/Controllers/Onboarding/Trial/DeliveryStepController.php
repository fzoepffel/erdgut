<?php

namespace App\Http\Controllers\Onboarding\Trial;

use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Requests\Onboarding\TrialDeliveryStepRequest;
use App\Services\Onboarding\OnboardingDraftManager;
use App\Support\DeliveryZoneResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeliveryStepController extends OnboardingController
{
    public function show(Request $request, DeliveryZoneResolver $zone): RedirectResponse|View
    {
        if ($request->user() === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);

        // C3 (UX notes): the picker now reflects what we actually run
        // (Wed 12-16 only) by reading from the zone resolver instead of
        // exposing all three slot bands from the Delivery enum.
        $zoneKey = $zone->resolveZoneKey($request->user()?->id);
        $timeslots = $zone->allowedTimeslots($zoneKey);

        // C4 (UX notes): the free-text "Wunschwoche / Wunschdatum"
        // field is now a real date picker. Bound to today + 1 day at
        // the earliest (no booking for the same day) and capped at the
        // scheduling-promise window (default 28 days). Operators get a
        // real date instead of free-form German.
        $promiseDays = (int) config('box.trial_scheduling_promise_days', 14);
        $minDate = CarbonImmutable::today()->addDay()->toDateString();
        $maxDate = CarbonImmutable::today()->addDays(max($promiseDays, 28))->toDateString();

        return view('onboarding.trial.delivery', [
            'draft' => $draft,
            'data' => $draft->data ?? [],
            'timeslots' => $timeslots,
            'promiseDays' => $promiseDays,
            'minPreferredDate' => $minDate,
            'maxPreferredDate' => $maxDate,
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
            'preferred_delivery_date' => $validated['preferred_delivery_date'] ?? null,
            'special_instructions' => $validated['special_instructions'] ?? null,
            'trial_delivery_completed' => true,
        ], OnboardingDraftManager::STEP_TRIAL_PAYMENT);

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_TRIAL_PAYMENT);
    }
}
