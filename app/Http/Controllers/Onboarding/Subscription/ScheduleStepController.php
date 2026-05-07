<?php

namespace App\Http\Controllers\Onboarding\Subscription;

use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Requests\Onboarding\SubscriptionScheduleStepRequest;
use App\Services\Onboarding\OnboardingDraftManager;
use App\Support\DeliveryZoneResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleStepController extends OnboardingController
{
    public function show(Request $request, DeliveryZoneResolver $zone): RedirectResponse|View
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);
        $data = $draft->data ?? [];
        $zoneKey = $zone->resolveZoneKey($user->id);
        $allowedWeekdays = $zone->allowedWeekdays($zoneKey);
        $timeslots = $zone->allowedTimeslots($zoneKey);

        // W2-11: pre-select the first valid weekday + timeslot so a
        // straight-through customer who clicks 'Continue to Payment' without
        // touching the dropdowns submits a valid value (the placeholder
        // 'No preference' option used to fail validation here).
        if (! isset($data['delivery_weekday']) && $allowedWeekdays !== []) {
            $data['delivery_weekday'] = (int) $allowedWeekdays[0];
        }
        if (empty($data['preferred_timeslot']) && $timeslots !== []) {
            $data['preferred_timeslot'] = (string) $timeslots[0];
        }

        return view('onboarding.subscription.schedule', [
            'draft' => $draft,
            'data' => $data,
            'plans' => [SubscriptionPlan::Weekly, SubscriptionPlan::Biweekly],
            'fulfillmentModes' => FulfillmentMode::cases(),
            'timeslots' => $timeslots,
            'allowedWeekdays' => $allowedWeekdays,
        ]);
    }

    public function store(SubscriptionScheduleStepRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('auth.login');
        }

        $draft = $this->drafts->currentOrCreate($request);
        $validated = $request->validated();

        // B10 (UX notes): delivery_instructions is no longer collected on
        // this step — the address step is the canonical home for the
        // delivery note. Drop the old delivery_instructions slot from
        // the draft so a stale value doesn't leak through if it was
        // saved by an earlier wizard pass.
        $this->drafts->update($draft, [
            'plan' => $validated['plan'],
            'fulfillment_mode' => $validated['fulfillment_mode'],
            'delivery_weekday' => $validated['delivery_weekday'] ?? null,
            'preferred_timeslot' => $validated['preferred_timeslot'] ?? null,
            'delivery_instructions' => null,
            'subscription_schedule_completed' => true,
        ], OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT);

        // Mirror the timeslot onto the customer's delivery preference. The
        // instructions field is left untouched here (address step owns
        // it); we still pull whatever the address record currently
        // holds so the per-user delivery_preferences stays in sync.
        $address = $user->primaryAddress()->first();
        $user->deliveryPreference()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_timeslot' => $validated['preferred_timeslot'] ?? null,
                'phone' => $user->phone,
                'instructions' => $address?->delivery_instructions,
            ]
        );

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT);
    }
}
