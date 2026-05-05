<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /**
     * The customer reached the SumUp payment step in the wizard but has
     * not yet stored a token. The Subscription row exists so the SumUp
     * checkout can reference its id, but no charging happens, no
     * subscriber-only nav opens, and no welcome dashboard treats this
     * as a real subscription.
     *
     * Active is the steady-state. PendingPayment subs flip to Active
     * inside SubscriptionOnboardingService::finalizeSetup() after the
     * mandate token is stored.
     */
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('status.'.$this->value);
    }
}
