<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionUpdateService
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService
    ) {}

    /**
     * Apply allowed subscription changes atomically and log an update event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Subscription $subscription, array $attributes, int $actorUserId): Subscription
    {
        $payload = Arr::only($attributes, ['box_size', 'preferences', 'allergies', 'plan', 'fulfillment_mode']);
        $payload['allergies'] = $payload['allergies'] ?? [];

        $before = [
            'box_size' => $subscription->box_size,
            'preferences' => $subscription->preferences ?? [],
            'allergies' => $subscription->allergies ?? [],
            'plan' => $subscription->plan,
            'fulfillment_mode' => $subscription->fulfillment_mode,
            'next_charge_date' => $subscription->next_charge_date?->toDateString(),
        ];

        return DB::transaction(function () use ($subscription, $payload, $before, $actorUserId): Subscription {
            $subscription->fill($payload);

            if ($subscription->isDirty('plan')
                && $subscription->status === SubscriptionStatus::Active) {
                $subscription->next_charge_date = $this->subscriptionLifecycleService->computeNextChargeDate($subscription);
            }

            $subscription->save();
            $subscription->refresh();

            $after = [
                'box_size' => $subscription->box_size,
                'preferences' => $subscription->preferences ?? [],
                'allergies' => $subscription->allergies ?? [],
                'plan' => $subscription->plan,
                'fulfillment_mode' => $subscription->fulfillment_mode,
                'next_charge_date' => $subscription->next_charge_date?->toDateString(),
            ];

            Log::info('subscription.updated', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'actor_user_id' => $actorUserId,
                'changed_fields' => $this->changedFields($before, $after),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $subscription;
        });
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        $fields = [];

        foreach ($before as $key => $value) {
            $afterValue = $after[$key] ?? null;
            if ($key === 'plan' || $key === 'fulfillment_mode') {
                $beforeVal = $value instanceof \BackedEnum ? $value->value : $value;
                $afterVal = $afterValue instanceof \BackedEnum ? $afterValue->value : $afterValue;
                if ($beforeVal !== $afterVal) {
                    $fields[] = $key;
                }

                continue;
            }

            if ($afterValue !== $value) {
                $fields[] = $key;
            }
        }

        return $fields;
    }
}
