<?php

namespace App\Services\Billing;

use App\Models\PaymentMandate;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AuditLogger;

/**
 * Manages the lifecycle of SumUp payment mandates (one per user).
 *
 * Mandates are user-scoped and survive subscription cancellation, so a user
 * who cancels and re-subscribes does not need to re-enter their card.
 *
 * All SumUp credential writes should go through this service. Legacy writes
 * directly on `subscriptions.sumup_token` / `sumup_customer_id` are kept as a
 * read-fallback in {@see SumUpCredentialResolver} until the backfill has
 * proven out in production.
 */
class SumUpMandateManager
{
    public const PROVIDER = 'sumup';

    /**
     * Get the user's active SumUp mandate, creating an empty one if none exists.
     * The returned mandate may have no token/customer yet — callers attach those
     * via {@see attachCustomer} / {@see storeToken} as the setup flow progresses.
     */
    public function ensureMandate(User $user): PaymentMandate
    {
        $existing = $user->paymentMandates()
            ->where('provider', self::PROVIDER)
            ->where('status', PaymentMandate::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $user->paymentMandates()->create([
            'provider' => self::PROVIDER,
            'status' => PaymentMandate::STATUS_ACTIVE,
        ]);
    }

    /**
     * Persist the SumUp customer id on the mandate. Safe to call multiple times.
     */
    public function attachCustomer(PaymentMandate $mandate, string $customerId): PaymentMandate
    {
        if ($mandate->provider_customer_id === $customerId) {
            return $mandate;
        }

        $mandate->forceFill(['provider_customer_id' => $customerId])->save();

        return $mandate;
    }

    /**
     * Persist the SumUp recurring token on the mandate. Safe to call multiple times.
     */
    public function storeToken(PaymentMandate $mandate, string $token): PaymentMandate
    {
        if ($mandate->provider_token === $token) {
            return $mandate;
        }

        $mandate->forceFill([
            'provider_token' => $token,
            'status' => PaymentMandate::STATUS_ACTIVE,
            'revoked_at' => null,
        ])->save();

        return $mandate;
    }

    /**
     * Link a subscription to a mandate so recurring charges resolve through it.
     */
    public function linkToSubscription(Subscription $subscription, PaymentMandate $mandate): void
    {
        if ($subscription->payment_mandate_id === $mandate->id) {
            return;
        }

        $subscription->forceFill(['payment_mandate_id' => $mandate->id])->save();
    }

    /**
     * Revoke the mandate (e.g. user removed their card). Idempotent.
     */
    public function revoke(PaymentMandate $mandate, ?string $reason = null, ?int $actorUserId = null): void
    {
        if (! $mandate->isActive()) {
            return;
        }

        $mandate->revoke();

        AuditLogger::log(
            entityType: PaymentMandate::class,
            entityId: $mandate->id,
            action: 'revoked',
            fromState: PaymentMandate::STATUS_ACTIVE,
            toState: PaymentMandate::STATUS_REVOKED,
            reason: $reason,
            actorUserId: $actorUserId,
        );
    }
}
