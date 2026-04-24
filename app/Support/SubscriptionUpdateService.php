<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        $payload = Arr::only($attributes, ['box_size', 'preferences', 'allergies', 'allergies_note', 'plan', 'fulfillment_mode', 'delivery_weekday', 'preferred_timeslot']);
        $payload['allergies'] = $payload['allergies'] ?? [];
        $payload['allergies_note'] = array_key_exists('allergies_note', $attributes) ? $attributes['allergies_note'] : $subscription->allergies_note;

        $before = [
            'box_size' => $subscription->box_size,
            'preferences' => $subscription->preferences ?? [],
            'allergies' => $subscription->allergies ?? [],
            'allergies_note' => $subscription->allergies_note,
            'plan' => $subscription->plan,
            'fulfillment_mode' => $subscription->fulfillment_mode,
            'delivery_weekday' => $subscription->delivery_weekday,
            'preferred_timeslot' => $subscription->preferred_timeslot instanceof \BackedEnum
                ? $subscription->preferred_timeslot->value
                : $subscription->preferred_timeslot,
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
                'allergies_note' => $subscription->allergies_note,
                'plan' => $subscription->plan,
                'fulfillment_mode' => $subscription->fulfillment_mode,
                'delivery_weekday' => $subscription->delivery_weekday,
                'preferred_timeslot' => $subscription->preferred_timeslot instanceof \BackedEnum
                    ? $subscription->preferred_timeslot->value
                    : $subscription->preferred_timeslot,
                'next_charge_date' => $subscription->next_charge_date?->toDateString(),
            ];

            AuditLogger::log(
                entityType: 'subscription',
                entityId: $subscription->id,
                action: 'plan_change',
                metadata: [
                    'subscription_user_id' => $subscription->user_id,
                    'changed_fields' => $this->changedFields($before, $after),
                ],
                actorUserId: $actorUserId,
            );

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
