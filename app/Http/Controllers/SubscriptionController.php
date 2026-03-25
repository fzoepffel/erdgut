<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\SubscriptionCancelRequest;
use App\Http\Requests\SubscriptionPauseRequest;
use App\Http\Requests\SubscriptionResumeRequest;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Models\Subscription;
use App\Support\SubscriptionLifecycleService;
use App\Support\SubscriptionUpdateService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): View
    {
        $subscription = $this->resolveSubscription($request);

        $nextChargeDate = $subscription?->next_charge_date?->format('d.m.Y');
        $billingDateDisplay = $nextChargeDate ?? __('Set after first charge');

        return view('subscriptions.show', [
            'subscription' => $subscription,
            'billingDateDisplay' => $billingDateDisplay,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('app.subscriptions.onboard');
    }

    public function store(SubscriptionStoreRequest $request): RedirectResponse
    {
        if ($this->resolveSubscription($request) !== null) {
            return redirect()->route('app.subscriptions.show');
        }

        $validated = $request->validated();

        Subscription::create([
            'user_id' => $request->user()->id,
            'plan' => $validated['plan'],
            'box_size' => $validated['box_size'],
            'preferences' => $validated['preferences'],
            'allergies' => $validated['allergies'] ?? [],
            'status' => SubscriptionStatus::Active,
        ]);

        return redirect()
            ->route('app.subscriptions.onboard.payment')
            ->with('status', 'subscription-created');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        return view('subscriptions.edit', [
            'subscription' => $subscription,
            'allergiesInput' => implode(', ', $subscription->allergies ?? []),
        ]);
    }

    public function update(
        SubscriptionUpdateRequest $request,
        SubscriptionUpdateService $subscriptionUpdateService
    ): RedirectResponse {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        $validated = $request->validated();

        $subscriptionUpdateService->update($subscription, $validated, $request->user()->id);

        return redirect()
            ->route('app.subscriptions.show')
            ->with('status', 'subscription-updated');
    }

    public function pause(
        SubscriptionPauseRequest $request,
        SubscriptionLifecycleService $subscriptionLifecycleService
    ): RedirectResponse {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        try {
            $subscriptionLifecycleService->schedulePause(
                $subscription,
                $request->validated()['pause_from'],
                $request->validated()['pause_until'],
                $request->user()->id
            );
        } catch (DomainException) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-action-not-allowed');
        }

        return redirect()
            ->route('app.subscriptions.show')
            ->with('status', 'subscription-pause-scheduled');
    }

    public function resume(
        SubscriptionResumeRequest $request,
        SubscriptionLifecycleService $subscriptionLifecycleService
    ): RedirectResponse {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        try {
            $subscriptionLifecycleService->resumeNow($subscription, $request->user()->id);
        } catch (DomainException) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-action-not-allowed');
        }

        return redirect()
            ->route('app.subscriptions.show')
            ->with('status', 'subscription-resumed');
    }

    public function cancel(
        SubscriptionCancelRequest $request,
        SubscriptionLifecycleService $subscriptionLifecycleService
    ): RedirectResponse {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        try {
            $subscriptionLifecycleService->cancelNow(
                $subscription,
                $request->validated()['cancellation_reason'] ?? null,
                $request->user()->id
            );
        } catch (DomainException) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-action-not-allowed');
        }

        return redirect()
            ->route('app.subscriptions.show')
            ->with('status', 'subscription-cancelled');
    }

    private function resolveSubscription(Request $request): ?Subscription
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return Subscription::billingSubscriptionForUser($user);
    }
}
