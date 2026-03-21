<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\SubscriptionLifecycleService;
use App\Support\SumUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentSetupController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        if (! empty($subscription->sumup_token)) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-already-setup');
        }

        $customerId = $this->ensureCustomer($request, $subscription, $this->sumUpService());

        if ($customerId === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-unavailable');
        }

        $checkoutId = $this->createSetupCheckout($subscription, $customerId, $this->sumUpService());

        if ($checkoutId === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-unavailable');
        }

        return view('payments.setup', [
            'subscription' => $subscription,
            'checkoutId' => $checkoutId,
        ]);
    }

    public function showUpdate(Request $request): View|RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        if (empty($subscription->sumup_token)) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-update-unavailable');
        }

        $customerId = $this->ensureCustomer($request, $subscription, $this->sumUpService());

        if ($customerId === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-unavailable');
        }

        $checkoutId = $this->createUpdateCheckout($subscription, $customerId, $this->sumUpService());

        if ($checkoutId === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-unavailable');
        }

        return view('payments.setup', [
            'subscription' => $subscription,
            'checkoutId' => $checkoutId,
            'intent' => 'update',
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        $checkoutId = (string) $request->query('checkout', '');

        if (trim($checkoutId) === '') {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-failed');
        }

        $intent = (string) $request->query('intent', 'setup');

        try {
            $checkout = $this->sumUpService()->getCheckout($checkoutId);
        } catch (Throwable $exception) {
            Log::warning('Failed to fetch SumUp checkout for payment setup completion.', [
                'subscription_id' => $subscription->id,
                'checkout_id' => $checkoutId,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-failed');
        }

        $checkoutReference = (string) data_get($checkout, 'checkout_reference', '');
        $expectedPrefix = 'sub-'.$subscription->id.'-';

        if (! str_starts_with($checkoutReference, $expectedPrefix)) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-failed');
        }

        $token = (string) data_get($checkout, 'payment_instrument.token', '');
        $customerId = (string) data_get($checkout, 'customer_id', '');

        if (trim($token) === '') {
            Log::warning('Payment setup incomplete: checkout has no payment token.', [
                'subscription_id' => $subscription->id,
                'checkout_id' => $checkoutId,
                'checkout_status' => data_get($checkout, 'status'),
            ]);

            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-setup-failed');
        }

        $subscriptionLifecycleService = app(SubscriptionLifecycleService::class);
        $nextChargeDate = $subscriptionLifecycleService->computeNextChargeDate($subscription);

        $subscription->update([
            'sumup_token' => $token,
            'sumup_customer_id' => $subscription->sumup_customer_id ?: $customerId,
            'next_charge_date' => $nextChargeDate,
        ]);

        if ($intent !== 'update') {
            $subscription->activate();
        }

        $status = $intent === 'update' ? 'payment-updated' : 'payment-setup-success';

        return redirect()
            ->route('app.subscriptions.show')
            ->with('status', $status);
    }

    private function resolveSubscription(Request $request): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByRaw('status = ? desc', [SubscriptionStatus::Active->value])
            ->latest()
            ->first();
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
            Log::warning('Failed to create SumUp customer during payment setup.', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customerId,
                'error' => $exception->getMessage(),
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
            Log::warning('Failed to create SumUp setup checkout.', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customerId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function createUpdateCheckout(Subscription $subscription, string $customerId, SumUpService $sumUpService): ?string
    {
        try {
            $checkout = $sumUpService->createSetupCheckout(
                customerId: $customerId,
                checkoutReference: 'sub-'.$subscription->id.'-update-'.now()->timestamp,
                description: 'Update payment method for subscription #'.$subscription->id
            );

            return data_get($checkout, 'id');
        } catch (Throwable $exception) {
            Log::warning('Failed to create SumUp update checkout.', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customerId,
                'error' => $exception->getMessage(),
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
