<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\User;

class BudgetRemainderService
{
    /**
     * Included subscription value and remainder (included minus estimated box value).
     *
     * @return array{included_value: float, remainder: float}|null
     */
    public function getBudgetDetails(User $user, Box $box): ?array
    {
        $subscription = $user->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->latest()
            ->first();

        if ($subscription === null) {
            return null;
        }

        $includedValue = $subscription->includedValueForBudget();

        if ($includedValue <= 0.0) {
            return null;
        }
        $estimatedValue = (float) $box->estimated_value;
        $remainder = round($includedValue - $estimatedValue, 2);

        return [
            'included_value' => $includedValue,
            'remainder' => $remainder,
        ];
    }

    /**
     * Get the budget remainder for a user's box.
     * Positive = under budget, negative = over budget.
     *
     * @return float|null The remainder in EUR, or null if no subscription/box size.
     */
    public function getRemainder(User $user, Box $box): ?float
    {
        $details = $this->getBudgetDetails($user, $box);

        return $details === null ? null : $details['remainder'];
    }
}
