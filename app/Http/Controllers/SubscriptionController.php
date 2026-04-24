<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionCancelRequest;
use App\Http\Requests\SubscriptionPauseRequest;
use App\Http\Requests\SubscriptionResumeRequest;
use App\Http\Requests\SubscriptionSkipWeeksRequest;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use App\Support\SubscriptionLifecycleService;
use App\Support\SubscriptionSkipService;
use App\Support\SubscriptionUpdateService;
use Carbon\CarbonImmutable;
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

        $skipPickerWeeks = [];
        $userSkippedWeeks = [];
        $pauseLockedWeeks = [];

        if ($subscription !== null) {
            $start = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
            for ($i = 0; $i < 12; $i++) {
                $skipPickerWeeks[] = $start->copy()->addWeeks($i);
            }

            $existing = $subscription->skipWeeks()
                ->whereBetween('week_start_date', [$skipPickerWeeks[0]->toDateString(), $skipPickerWeeks[11]->toDateString()])
                ->get();

            $userSkippedWeeks = $existing
                ->where('source', SubscriptionSkipWeek::SOURCE_USER)
                ->pluck('week_start_date')
                ->map(fn ($d) => $d->toDateString())
                ->all();

            $pauseLockedWeeks = $existing
                ->whereIn('source', [SubscriptionSkipWeek::SOURCE_PAUSE_WINDOW, SubscriptionSkipWeek::SOURCE_ADMIN])
                ->pluck('week_start_date')
                ->map(fn ($d) => $d->toDateString())
                ->all();
        }

        return view('subscriptions.show', [
            'subscription' => $subscription,
            'billingDateDisplay' => $billingDateDisplay,
            'skipPickerWeeks' => $skipPickerWeeks,
            'userSkippedWeeks' => $userSkippedWeeks,
            'pauseLockedWeeks' => $pauseLockedWeeks,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('app.onboarding.start', ['intent' => 'subscription']);
    }

    public function store(SubscriptionStoreRequest $request): RedirectResponse
    {
        if ($this->resolveSubscription($request) !== null) {
            return redirect()->route('app.subscriptions.show');
        }

        // Create-from-scratch via POST is superseded by the wizard; redirect there.
        return redirect()->route('app.onboarding.start', ['intent' => 'subscription']);
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

    public function skipNextWeek(
        Request $request,
        SubscriptionLifecycleService $subscriptionLifecycleService
    ): RedirectResponse {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        $nextMonday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
        $endOfNextWeek = $nextMonday->endOfWeek();

        try {
            $subscriptionLifecycleService->schedulePause(
                $subscription,
                $nextMonday->toDateString(),
                $endOfNextWeek->toDateString(),
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

    public function updateSkipWeeks(
        SubscriptionSkipWeeksRequest $request,
        SubscriptionSkipService $subscriptionSkipService
    ): RedirectResponse {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        $subscriptionSkipService->syncUserSkipWeeks(
            $subscription,
            $request->validated()['weeks'] ?? [],
            $request->user()->id
        );

        return redirect()
            ->route('app.subscriptions.show')
            ->with('status', 'subscription-skip-weeks-updated');
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
