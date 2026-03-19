<?php

namespace App\Support;

use App\Models\Subscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionUpdateService
{
    /**
     * Apply allowed subscription changes atomically and log an update event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Subscription $subscription, array $attributes, int $actorUserId): Subscription
    {
        $payload = Arr::only($attributes, ['box_size', 'preferences', 'allergies']);
        $payload['allergies'] = $payload['allergies'] ?? [];

        $before = [
            'box_size' => $subscription->box_size,
            'preferences' => $subscription->preferences ?? [],
            'allergies' => $subscription->allergies ?? [],
        ];

        return DB::transaction(function () use ($subscription, $payload, $before, $actorUserId): Subscription {
            $subscription->fill($payload);
            $subscription->save();
            $subscription->refresh();

            $after = [
                'box_size' => $subscription->box_size,
                'preferences' => $subscription->preferences ?? [],
                'allergies' => $subscription->allergies ?? [],
            ];

            Log::info('subscription.updated', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'actor_user_id' => $actorUserId,
                'changed_fields' => $this->changedFields($before, $after),
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
            if (($after[$key] ?? null) !== $value) {
                $fields[] = $key;
            }
        }

        return $fields;
    }
}
