<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Http\Requests\DeliveryPreferencesUpdateRequest;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryPreferencesController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $address = $user->primaryAddress()->first();
        $deliveryPreference = $user->deliveryPreference()->first();

        $upcomingDeliveryCount = Delivery::query()
            ->where('user_id', $user->id)
            ->where('status', DeliveryStatus::Scheduled)
            ->whereDate('scheduled_for_date', '>=', now()->toDateString())
            ->count();

        $subscription = Subscription::billingSubscriptionForUser($user);
        $onFileSince = $address?->created_at;

        return view('deliveries.preferences', [
            'address' => $address,
            'deliveryPreference' => $deliveryPreference,
            'upcomingDeliveryCount' => $upcomingDeliveryCount,
            'subscription' => $subscription,
            'onFileSince' => $onFileSince,
        ]);
    }

    public function update(DeliveryPreferencesUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $addressPayload = [
                'label' => ! empty($validated['doorbell']) ? $validated['doorbell'] : 'Primary',
                'recipient_name' => $validated['recipient_name'],
                'street_line_1' => $validated['street_line_1'],
                'street_line_2' => $validated['street_line_2'] ?? null,
                'postal_code' => $validated['postal_code'],
                'city' => $validated['city'],
                'country_code' => $validated['country_code'],
                'phone' => $validated['phone'] ?? null,
                'building_code' => $validated['building_code'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'delivery_instructions' => $validated['instructions'] ?? null,
                'is_primary' => true,
            ];

            $address = $user->primaryAddress()->first();

            if ($address instanceof Address) {
                $address->update($addressPayload);
            } else {
                $user->addresses()->create($addressPayload);
            }

            $user->deliveryPreference()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'preferred_timeslot' => $validated['preferred_timeslot'],
                    'phone' => $validated['phone'] ?? null,
                    'instructions' => $validated['instructions'] ?? null,
                ]
            );
        });

        return redirect()
            ->route('app.deliveries.preferences.edit')
            ->with('status', 'delivery-preferences-updated');
    }
}
