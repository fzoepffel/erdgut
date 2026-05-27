<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBillingChargeRequest;
use App\Models\Subscription;
use App\Services\Admin\BillingCoverageService;
use App\Services\Billing\SubscriptionRecurringChargeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Throwable;

class AdminBillingCoverageController extends Controller
{
    public function index(BillingCoverageService $billingCoverageService): View
    {
        $this->authorize('admin');

        $matrix = $billingCoverageService->buildMatrix(Carbon::today());

        return view('admin.operations.billing.coverage', [
            'weeks' => $matrix['weeks'],
            'rows' => $matrix['rows'],
        ]);
    }

    public function charge(
        AdminBillingChargeRequest $request,
        SubscriptionRecurringChargeService $recurringChargeService
    ): RedirectResponse {
        $this->authorize('admin');

        $validated = $request->validated();
        $subscription = Subscription::query()->findOrFail($validated['subscription_id']);
        $week = Carbon::parse($validated['week_start_date'])->startOfDay();
        $currency = (string) config('sumup.subscription.currency', 'EUR');

        try {
            $recurringChargeService->initiate(
                subscription: $subscription,
                amount: $subscription->expectedChargeAmount(),
                currency: $currency,
                initiator: PaymentChargeInitiator::AdminManual,
                checkoutDescription: __('admin.billing_charge.checkout_description', ['id' => $subscription->id]),
                initiatedByUserId: (int) $request->user()->id,
                boxId: SubscriptionRecurringChargeService::resolveBoxIdForUserWeek((int) $subscription->user_id, $week),
                fulfillmentWeekStart: $week,
                previousPaymentId: null,
            );
        } catch (Throwable) {
            return redirect()
                ->route('app.admin.billing.coverage')
                ->withErrors(['charge' => __('admin.billing_charge.flash_failed')]);
        }

        return redirect()
            ->route('app.admin.billing.coverage')
            ->with('status', __('admin.billing_charge.flash_started'));
    }
}
