<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\User;

class BudgetRemainderService
{
    /**
     * Get the budget remainder for a user's box.
     * Positive = under budget, negative = over budget.
     *
     * @return float|null The remainder in EUR, or null if no subscription/box size.
     */
    public function getRemainder(User $user, Box $box): ?float
    {
        $subscription = $user->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->latest()
            ->first();

        if ($subscription === null) {
            return null;
        }

        $includedValue = config('box.included_value_by_size.'.$subscription->box_size);

        if ($includedValue === null || ! is_numeric($includedValue)) {
            return null;
        }

        $estimatedValue = (float) $box->estimated_value;

        return round((float) $includedValue - $estimatedValue, 2);
    }
}
