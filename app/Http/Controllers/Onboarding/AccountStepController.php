<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Requests\Onboarding\AccountStepRequest;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountStepController extends OnboardingController
{
    public function show(Request $request): RedirectResponse|View
    {
        $draft = $this->drafts->currentOrCreate($request);

        if ($request->user() !== null) {
            // Already authed -> step A is no-op; mark completed and move on.
            $this->drafts->update($draft, ['account_completed' => true], OnboardingDraftManager::STEP_ADDRESS);

            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ADDRESS);
        }

        return view('onboarding.account', [
            'draft' => $draft,
        ]);
    }

    public function store(AccountStepRequest $request): RedirectResponse
    {
        $draft = $this->drafts->currentOrCreate($request);
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));
        Auth::login($user);

        // Re-associate draft with the new user id, then mark step complete.
        $draft->update(['user_id' => $user->id]);
        $this->drafts->update($draft, ['account_completed' => true], OnboardingDraftManager::STEP_ADDRESS);

        return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ADDRESS);
    }
}
