<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Models\Subscription;
use App\Support\SubscriptionLifecycleService;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SubscriptionOnboardController extends Controller
{
    public function showStep1(Request $request): View|RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription !== null && ! empty($subscription->sumup_token)) {
            return redirect()->route('app.dashboard');
        }

        if ($subscription !== null && empty($subscription->sumup_token)) {
            return redirect()->route('app.subscriptions.onboard.payment');
        }

        $boxSize = $request->query('box_size', 'medium');
        if (! in_array($boxSize, ['small', 'medium', 'large'], true)) {
            $boxSize = 'medium';
        }

        $subscription = new Subscription([
            'box_size' => $boxSize,
            'plan' => SubscriptionPlan::Weekly,
            'preferences' => [
                'veg_only' => false,
                'meat_add_on' => false,
                'eggs_dairy_add_on' => false,
            ],
            'allergies' => [],
        ]);

        return view('subscriptions.onboard-step1', [
            'subscription' => $subscription,
            'selectedAllergens' => [],
            'allergiesNoteValue' => '',
        ]);
    }

    public function storeStep1(SubscriptionStoreRequest $request): RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription !== null) {
            return redirect()->route('app.subscriptions.onboard.payment');
        }

        $validated = $request->validated();

        Subscription::create([
            'user_id' => $request->user()->id,
            'plan' => $validated['plan'],
            'box_size' => $validated['box_size'],
            'preferences' => $validated['preferences'],
            'allergies' => $validated['allergies'] ?? [],
            'allergies_note' => $validated['allergies_note'] ?? null,
            'delivery_weekday' => $validated['delivery_weekday'] ?? null,
            'preferred_timeslot' => $validated['preferred_timeslot'] ?? null,
            'status' => SubscriptionStatus::Active,
        ]);

        return redirect()->route('app.subscriptions.onboard.payment');
    }

    public function showStep2(Request $request): View|RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()->route('app.subscriptions.onboard');
        }

        if (! empty($subscription->sumup_token)) {
            return redirect()
                ->route('app.dashboard')
                ->with('status', 'payment-already-setup');
        }

        $customerId = $this->ensureCustomer($request, $subscription, $this->sumUpService());

        if ($customerId === null) {
            return redirect()
                ->route('app.subscriptions.onboard')
                ->with('status', 'payment-setup-unavailable');
        }

        $checkoutId = $this->createSetupCheckout($subscription, $customerId, $this->sumUpService());

        if ($checkoutId === null) {
            return redirect()
                ->route('app.subscriptions.onboard')
                ->with('status', 'payment-setup-unavailable');
        }

        SumUpCheckoutLogger::info('Onboard payment setup: SumUp checkout created for widget.', [
            'flow' => 'onboard_setup',
            'user_id' => $request->user()->id,
            'subscription_id' => $subscription->id,
            'sumup_customer_id' => $customerId,
            'checkout_id' => $checkoutId,
        ]);

        return view('subscriptions.onboard-step2', [
            'subscription' => $subscription,
            'checkoutId' => $checkoutId,
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.onboard')
                ->with('status', 'subscription-missing');
        }

        $checkoutId = (string) ($request->query('checkout', '') ?: $request->query('checkout_id', ''));

        if (trim($checkoutId) === '') {
            SumUpCheckoutLogger::warning('Onboard payment complete: missing checkout query parameter.', [
                'user_id' => $request->user()->id,
                'subscription_id' => $subscription->id,
            ]);

            return redirect()
                ->route('app.subscriptions.onboard.payment')
                ->with('status', 'payment-setup-failed');
        }

        SumUpCheckoutLogger::info('Onboard payment complete: fetching checkout from SumUp.', [
            'user_id' => $request->user()->id,
            'subscription_id' => $subscription->id,
            'checkout_id' => $checkoutId,
        ]);

        try {
            $checkout = $this->sumUpService()->getCheckout($checkoutId);
        } catch (Throwable $exception) {
            SumUpCheckoutLogger::warning('Failed to fetch SumUp checkout for onboard payment completion.', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id,
                'checkout_id' => $checkoutId,
                'exception_class' => $exception::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($exception->getMessage()),
            ]);

            return redirect()
                ->route('app.subscriptions.onboard.payment')
                ->with('status', 'payment-setup-failed');
        }

        $snapshot = SumUpCheckoutLogger::checkoutSnapshot($checkout);

        SumUpCheckoutLogger::info('Onboard payment complete: checkout retrieved.', [
            'subscription_id' => $subscription->id,
            'checkout_id' => $checkoutId,
            'checkout' => $snapshot,
        ]);

        $checkoutStatus = (string) data_get($checkout, 'status', '');

        $checkoutReference = (string) (data_get($checkout, 'checkoutReference') ?? data_get($checkout, 'checkout_reference', ''));
        $expectedPrefix = 'sub-'.$subscription->id.'-';

        if ($checkoutReference !== '' && ! str_starts_with($checkoutReference, $expectedPrefix)) {
            SumUpCheckoutLogger::warning('Onboard payment complete: checkout_reference does not match subscription.', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id,
                'checkout_id' => $checkoutId,
                'expected_prefix' => $expectedPrefix,
                'checkout_reference' => $checkoutReference,
            ]);

            return redirect()
                ->route('app.subscriptions.onboard.payment')
                ->with('status', 'payment-setup-failed');
        }

        $tokenSource = null;
        $token = (string) (data_get($checkout, 'paymentInstrument.token') ?? data_get($checkout, 'payment_instrument.token', ''));
        $customerId = (string) (data_get($checkout, 'customerId') ?? data_get($checkout, 'customer_id', ''));

        if (trim($token) !== '') {
            $tokenSource = 'checkout.payment_instrument';
        }

        // Fallback: checkout may not include payment_instrument for SETUP_RECURRING_PAYMENT;
        // retrieve token from customer's payment instruments after successful checkout
        if (trim($token) === '' && trim($customerId) !== '') {
            try {
                $instruments = $this->sumUpService()->listPaymentInstruments($customerId);
                $instrumentList = is_array($instruments) ? $instruments : (array) $instruments;
                $firstInstrument = $instrumentList[0] ?? null;
                $token = (string) data_get($firstInstrument, 'token', '');
                if (trim($token) !== '') {
                    $tokenSource = 'list_payment_instruments';
                }

                SumUpCheckoutLogger::info('Onboard payment: listed payment instruments for token fallback.', [
                    'subscription_id' => $subscription->id,
                    'sumup_customer_id' => $customerId,
                    'instrument_count' => count($instrumentList),
                    'token_resolved' => trim($token) !== '',
                ]);
            } catch (Throwable $exception) {
                SumUpCheckoutLogger::warning('Failed to list payment instruments for token fallback.', [
                    'subscription_id' => $subscription->id,
                    'sumup_customer_id' => $customerId,
                    'checkout_id' => $checkoutId,
                    'exception_class' => $exception::class,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($exception->getMessage()),
                ]);
            }
        }

        if (trim($token) === '') {
            SumUpCheckoutLogger::warning('Onboard payment incomplete: no payment token after checkout and fallback.', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id,
                'checkout_id' => $checkoutId,
                'checkout_status' => $checkoutStatus,
                'sumup_customer_id' => $customerId !== '' ? $customerId : null,
                'checkout' => $snapshot,
            ]);

            return redirect()
                ->route('app.subscriptions.onboard.payment')
                ->with('status', 'payment-setup-failed');
        }

        // SETUP_RECURRING_PAYMENT checkouts often report status FAILED because
        // the 1 EUR authorization is reversed, but SumUp still issues a reusable
        // token.  This is expected — we only care that a token was obtained.
        if ($checkoutStatus === 'FAILED') {
            SumUpCheckoutLogger::info('Onboard payment: checkout status FAILED but token obtained — proceeding (expected for SETUP_RECURRING_PAYMENT).', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id,
                'checkout_id' => $checkoutId,
                'token_source' => $tokenSource,
            ]);
        }

        $subscriptionLifecycleService = app(SubscriptionLifecycleService::class);
        $nextChargeDate = $subscriptionLifecycleService->computeNextChargeDate($subscription);

        $subscription->update([
            'sumup_token' => $token,
            'sumup_customer_id' => $subscription->sumup_customer_id ?: $customerId,
            'next_charge_date' => $nextChargeDate,
        ]);

        $subscription->activate();

        SumUpCheckoutLogger::info('Onboard payment setup completed successfully.', [
            'subscription_id' => $subscription->id,
            'user_id' => $request->user()->id,
            'checkout_id' => $checkoutId,
            'token_source' => $tokenSource,
            'sumup_customer_id' => $subscription->sumup_customer_id ?: $customerId,
        ]);

        return redirect()
            ->route('app.dashboard')
            ->with('status', 'subscription-onboarded');
    }

    private function resolveSubscription(Request $request): ?Subscription
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return Subscription::billingSubscriptionForUser($user);
    }

    private function ensureCustomer(Request $request, Subscription $subscription, SumUpService $sumUpService): ?string
    {
        if (! empty($subscription->sumup_customer_id)) {
            return (string) $subscription->sumup_customer_id;
        }

        $customerId = 'sub-'.$subscription->id;
        $user = $request->user();
        $address = $user->primaryAddress()->first();
        [$firstName, $lastName] = $this->splitName((string) $user->name);

        $payload = [
            'customer_id' => $customerId,
            'personal_details' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => (string) $user->email,
                'phone' => (string) ($user->phone ?? ''),
            ],
        ];

        if ($address !== null) {
            $payload['personal_details']['address'] = array_filter([
                'line_1' => (string) $address->street_line_1,
                'line_2' => (string) $address->street_line_2,
                'city' => (string) $address->city,
                'postal_code' => (string) $address->postal_code,
                'country' => (string) $address->country_code,
            ], static fn ($value): bool => trim((string) $value) !== '');
        }

        try {
            $sumUpService->createCustomer($payload);
        } catch (Throwable $exception) {
            SumUpCheckoutLogger::warning('Failed to create SumUp customer during onboard payment setup.', [
                'subscription_id' => $subscription->id,
                'sumup_customer_id' => $customerId,
                'exception_class' => $exception::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($exception->getMessage()),
            ]);
        }

        $subscription->update(['sumup_customer_id' => $customerId]);

        return $customerId;
    }

    private function createSetupCheckout(Subscription $subscription, string $customerId, SumUpService $sumUpService): ?string
    {
        try {
            $checkout = $sumUpService->createSetupCheckout(
                customerId: $customerId,
                checkoutReference: 'sub-'.$subscription->id.'-setup-'.now()->timestamp,
                description: 'Setup recurring payment for subscription #'.$subscription->id
            );

            return data_get($checkout, 'id');
        } catch (Throwable $exception) {
            SumUpCheckoutLogger::warning('Failed to create SumUp setup checkout during onboarding.', [
                'subscription_id' => $subscription->id,
                'sumup_customer_id' => $customerId,
                'exception_class' => $exception::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($exception->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        $firstName = (string) ($parts[0] ?? 'Customer');
        $lastName = (string) ($parts[1] ?? 'Erdgut');

        return [$firstName, $lastName];
    }

    private function sumUpService(): SumUpService
    {
        return app(SumUpService::class);
    }
}
