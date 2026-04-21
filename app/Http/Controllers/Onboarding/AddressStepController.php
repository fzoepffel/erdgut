<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Requests\Onboarding\AddressStepRequest;
use App\Models\Address;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressStepController extends OnboardingController
{
    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->currentOrCreate($request);
        $data = $draft->data ?? [];

        $existingAddress = $user->primaryAddress()->first();

        return view('onboarding.address', [
            'draft' => $draft,
            'address' => $existingAddress,
            'prefill' => $data,
        ]);
    }

    public function store(AddressStepRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('auth.login');
        }

        $validated = $request->validated();
        $draft = $this->drafts->currentOrCreate($request);

        $payload = [
            'label' => 'Primary',
            'recipient_name' => $validated['recipient_name'],
            'street_line_1' => $validated['street_line_1'],
            'street_line_2' => $validated['street_line_2'] ?? null,
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'country_code' => $validated['country_code'],
            'phone' => $validated['phone'] ?? null,
            'building_code' => $validated['building_code'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'delivery_instructions' => $validated['delivery_instructions'] ?? null,
            'is_primary' => true,
        ];

        $address = $user->primaryAddress()->first();

        if ($address instanceof Address) {
            $address->update($payload);
        } else {
            $address = $user->addresses()->create($payload);
        }

        if (! empty($validated['phone']) && empty($user->phone)) {
            $user->update(['phone' => $validated['phone']]);
        }

        $this->drafts->update($draft, [
            'address_id' => $address->id,
            'address_completed' => true,
        ], OnboardingDraftManager::STEP_PREFERENCES);

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_PREFERENCES);
    }
}
