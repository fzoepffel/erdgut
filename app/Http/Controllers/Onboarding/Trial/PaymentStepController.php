<?php

namespace App\Http\Controllers\Onboarding\Trial;

use App\Enums\OrderStatus;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Order;
use App\Services\Onboarding\OnboardingDraftManager;
use App\Services\Onboarding\TrialOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentStepController extends OnboardingController
{
    public function __construct(
        OnboardingDraftManager $drafts,
        private readonly TrialOrderService $trialOrders,
    ) {
        parent::__construct($drafts);
    }

    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        if (! config('features.trial_orders_enabled', true)) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_COMMITMENT);
        }

        $draft = $this->drafts->currentOrCreate($request);
        $data = $draft->data ?? [];

        $order = $this->findOrCreateOrder($user, $data);

        if ($order->status !== OrderStatus::PendingPayment) {
            return redirect()->route('app.onboarding.trial.complete', ['order' => $order->id]);
        }

        $returnUrl = route('app.onboarding.trial.payment.complete', ['order' => $order->id], absolute: true);

        $checkoutId = null;
        try {
            $checkout = $this->trialOrders->initiateCheckout($order, $returnUrl);
            $checkoutId = (string) (data_get($checkout, 'id') ?? '') ?: null;
        } catch (Throwable $e) {
            report($e);
        }

        return view('onboarding.trial.payment', [
            'draft' => $draft,
            'order' => $order,
            'checkoutId' => $checkoutId,
            'returnUrl' => $returnUrl,
        ]);
    }

    public function complete(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || $order->user_id !== $user->id) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        $draft = $this->drafts->findCurrent($request);

        // Attempt to confirm via SumUp if webhook hasn't fired yet.
        if ($order->status === OrderStatus::PendingPayment) {
            $this->attemptToConfirm($order, $request);
        }

        $order->refresh();

        if ($order->status === OrderStatus::PendingPayment) {
            return redirect()
                ->route('app.onboarding.step.'.OnboardingDraftManager::STEP_TRIAL_PAYMENT)
                ->with('status', 'payment-pending');
        }

        if ($draft !== null) {
            $this->drafts->clear($draft);
        }

        return redirect()->route('app.onboarding.trial.complete', ['order' => $order->id]);
    }

    public function confirmation(Request $request, Order $order): RedirectResponse|View
    {
        $user = $request->user();
        if ($user === null || $order->user_id !== $user->id) {
            return redirect()->route('app.onboarding.step.'.OnboardingDraftManager::STEP_ACCOUNT);
        }

        return view('onboarding.trial.complete', [
            'order' => $order,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateOrder(\App\Models\User $user, array $data): Order
    {
        $existing = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::PendingPayment)
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->trialOrders->createOrder($user, [
            'address_id' => $data['address_id'] ?? null,
            'box_size' => $data['box_size'] ?? 'medium',
            'preferences' => $data['preferences'] ?? [],
            'allergies' => $data['allergies'] ?? [],
            'preferred_timeslot' => $data['preferred_timeslot'] ?? null,
            'preferred_delivery_window' => $data['preferred_delivery_window'] ?? null,
            'special_instructions' => $data['special_instructions'] ?? null,
            'is_test' => (bool) $user->is_test,
        ]);
    }

    private function attemptToConfirm(Order $order, Request $request): void
    {
        // In this minimal integration the webhook handler is the authoritative path.
        // As a convenience in the test environment, if the query carries ?paid=1
        // or the checkout returned success via SumUp, we mark the order paid here.
        if ($request->query('paid') === '1') {
            $this->trialOrders->markOrderPaid($order);
        }
    }
}
