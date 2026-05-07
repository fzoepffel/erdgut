<?php

namespace App\Http\Controllers\Onboarding\Trial;

use App\Enums\OrderStatus;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Order;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use App\Services\Onboarding\TrialOrderService;
use App\Support\SumUpCheckoutLogger;
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
        $checkoutError = null;
        try {
            $checkout = $this->trialOrders->initiateCheckout($order, $returnUrl);
            $checkoutId = (string) (data_get($checkout, 'id') ?? '') ?: null;

            if ($checkoutId === null) {
                // SumUp returned without a usable id; surface a clean error instead of a blank widget area.
                $checkoutError = 'no_checkout_id';
                SumUpCheckoutLogger::warning('Trial payment step: SumUp returned no checkout id.', [
                    'order_id' => $order->id,
                    'return_url' => $returnUrl,
                ]);
            }
        } catch (Throwable $e) {
            report($e);
            $checkoutError = 'checkout_creation_failed';
            SumUpCheckoutLogger::warning('Trial payment step: SumUp checkout creation failed.', [
                'order_id' => $order->id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }

        return view('onboarding.trial.payment', [
            'draft' => $draft,
            'order' => $order,
            'checkoutId' => $checkoutId,
            'checkoutError' => $checkoutError,
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

        // Webhook is authoritative; the return-url only triggers a server-side
        // verification against SumUp in case the webhook hasn't fired yet.
        if ($order->status === OrderStatus::PendingPayment) {
            $this->trialOrders->confirmPaidFromProvider($order);
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
    private function findOrCreateOrder(User $user, array $data): Order
    {
        $existing = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::PendingPayment)
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // C4 (UX notes): the trial delivery step now writes a real ISO
        // date to `preferred_delivery_date`; older drafts may still
        // hold a free-text `preferred_delivery_window`. Pass whatever
        // is set into the legacy column on the Order — the column name
        // stays for back-compat across admin views, emails, and the
        // already-shipped TrialOrder model.
        return $this->trialOrders->createOrder($user, [
            'address_id' => $data['address_id'] ?? null,
            'box_size' => $data['box_size'] ?? 'medium',
            'preferences' => $data['preferences'] ?? [],
            'allergies' => $data['allergies'] ?? [],
            'preferred_timeslot' => $data['preferred_timeslot'] ?? null,
            'preferred_delivery_window' => $data['preferred_delivery_date']
                ?? ($data['preferred_delivery_window'] ?? null),
            'special_instructions' => $data['special_instructions'] ?? null,
            'is_test' => (bool) $user->is_test,
        ]);
    }
}
