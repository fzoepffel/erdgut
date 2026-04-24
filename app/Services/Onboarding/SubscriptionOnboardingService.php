<?php

namespace App\Services\Onboarding;

use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SumUpMandateManager;
use App\Support\SubscriptionLifecycleService;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Throwable;

/**
 * Adapts the legacy two-step SubscriptionOnboardController logic into reusable
 * service methods called from the unified onboarding wizard.
 */
class SubscriptionOnboardingService
{
    public function __construct(
        private readonly SumUpService $sumUpService,
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly SumUpMandateManager $mandateManager,
    ) {}

    /**
     * Create (or return existing) subscription for the user in Active state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ensureSubscription(User $user, array $payload): Subscription
    {
        $existing = Subscription::billingSubscriptionForUser($user);

        if ($existing !== null && $existing->status !== SubscriptionStatus::Cancelled) {
            return $existing;
        }

        $plan = $payload['plan'] instanceof SubscriptionPlan
            ? $payload['plan']
            : SubscriptionPlan::from((string) ($payload['plan'] ?? SubscriptionPlan::Weekly->value));

        $fulfillmentMode = $payload['fulfillment_mode'] ?? null;
        if (is_string($fulfillmentMode) && $fulfillmentMode !== '') {
            $fulfillmentMode = FulfillmentMode::tryFrom($fulfillmentMode) ?? FulfillmentMode::Delivery;
        } elseif (! $fulfillmentMode instanceof FulfillmentMode) {
            $fulfillmentMode = FulfillmentMode::Delivery;
        }

        $weekday = $payload['delivery_weekday'] ?? null;
        $weekday = is_numeric($weekday) ? (int) $weekday : null;

        return Subscription::create([
            'user_id' => $user->id,
            'plan' => $plan,
            'box_size' => $payload['box_size'] ?? 'medium',
            'preferences' => $payload['preferences'] ?? [],
            'allergies' => $payload['allergies'] ?? [],
            'status' => SubscriptionStatus::Active,
            'fulfillment_mode' => $fulfillmentMode,
            'delivery_weekday' => $weekday,
            'preferred_timeslot' => $payload['preferred_timeslot'] ?? null,
        ]);
    }

    /**
     * Create a SumUp customer (if missing) and a SETUP_RECURRING_PAYMENT checkout.
     *
     * Returns the checkout id to render in the SumUp widget, or null on failure.
     */
    public function createSetupCheckoutForSubscription(Subscription $subscription, User $user): ?string
    {
        $customerId = $this->ensureCustomer($subscription, $user);

        if ($customerId === null) {
            return null;
        }

        try {
            $checkout = $this->sumUpService->createSetupCheckout(
                customerId: $customerId,
                checkoutReference: 'sub-'.$subscription->id.'-setup-'.now()->timestamp,
                description: 'Setup recurring payment for subscription #'.$subscription->id
            );

            return (string) (data_get($checkout, 'id') ?? '') ?: null;
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('Failed to create SumUp setup checkout during onboarding wizard.', [
                'subscription_id' => $subscription->id,
                'sumup_customer_id' => $customerId,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * Finalize the subscription mandate after SumUp returns the customer.
     *
     * Returns true on success, false if the token could not be obtained.
     */
    public function finalizeSetup(Subscription $subscription, string $checkoutId): bool
    {
        try {
            $checkout = $this->sumUpService->getCheckout($checkoutId);
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('Onboarding wizard: failed to fetch SumUp checkout.', [
                'subscription_id' => $subscription->id,
                'checkout_id' => $checkoutId,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);

            return false;
        }

        $checkoutReference = (string) (data_get($checkout, 'checkoutReference') ?? data_get($checkout, 'checkout_reference', ''));
        $expectedPrefix = 'sub-'.$subscription->id.'-';

        if ($checkoutReference !== '' && ! str_starts_with($checkoutReference, $expectedPrefix)) {
            return false;
        }

        $token = (string) (data_get($checkout, 'paymentInstrument.token') ?? data_get($checkout, 'payment_instrument.token', ''));
        $customerId = (string) (data_get($checkout, 'customerId') ?? data_get($checkout, 'customer_id', ''));

        if (trim($token) === '' && trim($customerId) !== '') {
            try {
                $instruments = $this->sumUpService->listPaymentInstruments($customerId);
                $list = is_array($instruments) ? $instruments : (array) $instruments;
                $first = $list[0] ?? null;
                $token = (string) data_get($first, 'token', '');
            } catch (Throwable $e) {
                SumUpCheckoutLogger::warning('Onboarding wizard: listPaymentInstruments fallback failed.', [
                    'subscription_id' => $subscription->id,
                    'sumup_customer_id' => $customerId,
                    'exception_class' => $e::class,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                ]);
            }
        }

        if (trim($token) === '') {
            return false;
        }

        $resolvedCustomerId = $subscription->sumup_customer_id ?: $customerId;

        $mandate = $this->mandateManager->ensureMandate($subscription->user);
        $this->mandateManager->attachCustomer($mandate, (string) $resolvedCustomerId);
        $this->mandateManager->storeToken($mandate, $token);
        $this->mandateManager->linkToSubscription($subscription, $mandate);

        $nextChargeDate = $this->lifecycle->computeNextChargeDate($subscription);

        $subscription->update([
            'sumup_token' => $token,
            'sumup_customer_id' => $resolvedCustomerId,
            'next_charge_date' => $nextChargeDate,
        ]);

        $subscription->activate();

        return true;
    }

    private function ensureCustomer(Subscription $subscription, User $user): ?string
    {
        if (! empty($subscription->sumup_customer_id)) {
            return (string) $subscription->sumup_customer_id;
        }

        $customerId = 'sub-'.$subscription->id;
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
            $this->sumUpService->createCustomer($payload);
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('Onboarding wizard: failed to create SumUp customer.', [
                'subscription_id' => $subscription->id,
                'sumup_customer_id' => $customerId,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }

        $mandate = $this->mandateManager->ensureMandate($user);
        $this->mandateManager->attachCustomer($mandate, $customerId);
        $this->mandateManager->linkToSubscription($subscription, $mandate);

        $subscription->update(['sumup_customer_id' => $customerId]);

        return $customerId;
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
}
