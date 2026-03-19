<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Models\Subscription;
use App\Support\SubscriptionUpdateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): View
    {
        $subscription = $this->resolveSubscription($request);

        // TODO(ERD-090): Replace billing-date placeholder with computed recurring billing date.
        $billingDatePlaceholder = 'Billing date available after payment setup.';

        return view('subscriptions.show', [
            'subscription' => $subscription,
            'billingDatePlaceholder' => $billingDatePlaceholder,
        ]);
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
    ): RedirectResponse
    {
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

    private function resolveSubscription(Request $request): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByRaw('status = ? desc', [SubscriptionStatus::Active->value])
            ->latest()
            ->first();
    }
}
