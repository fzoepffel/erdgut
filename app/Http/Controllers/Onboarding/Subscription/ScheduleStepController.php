<?php

namespace App\Http\Controllers\Onboarding\Subscription;

use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Requests\Onboarding\SubscriptionScheduleStepRequest;
use App\Models\Delivery;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleStepController extends OnboardingController
{
    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);
        $data = $draft->data ?? [];

        return view('onboarding.subscription.schedule', [
            'draft' => $draft,
            'data' => $data,
            'plans' => [SubscriptionPlan::Weekly, SubscriptionPlan::Biweekly],
            'fulfillmentModes' => FulfillmentMode::cases(),
            'timeslots' => Delivery::allowedTimeslots(),
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

        $this->drafts->update($draft, [
            'plan' => $validated['plan'],
            'fulfillment_mode' => $validated['fulfillment_mode'],
            'preferred_timeslot' => $validated['preferred_timeslot'] ?? null,
            'delivery_instructions' => $validated['delivery_instructions'] ?? null,
            'subscription_schedule_completed' => true,
        ], OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT);

        // Persist delivery preference (and update address instructions) for subscription users.
        $user->deliveryPreference()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_timeslot' => $validated['preferred_timeslot'] ?? null,
                'phone' => $user->phone,
                'instructions' => $validated['delivery_instructions'] ?? null,
            ]
        );

        $address = $user->primaryAddress()->first();
        if ($address !== null && isset($validated['delivery_instructions'])) {
            $address->update(['delivery_instructions' => $validated['delivery_instructions']]);
        }

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT);
    }
}
