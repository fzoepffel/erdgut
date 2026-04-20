<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Http\Requests\GiftDeliveryRequest;
use App\Models\Delivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $today = now()->toDateString();

        $baseDeliveries = Delivery::query()
            ->where('user_id', $user->id)
            ->where('is_weekly_override', false)
            ->where('scheduled_for_date', '>=', $today)
            ->with([
                'address',
                'overrideDeliveries' => fn ($query) => $query->latest(),
                'overrideDeliveries.address',
            ])
            ->orderBy('scheduled_for_date')
            ->limit(4)
            ->get();

        $weeklyDeliveries = $baseDeliveries->map(function (Delivery $baseDelivery): array {
            $overrideDelivery = $baseDelivery->overrideDeliveries->first();
            $effectiveDelivery = $overrideDelivery ?? $baseDelivery;

            return [
                'base' => $baseDelivery,
                'effective' => $effectiveDelivery,
                'hasOverride' => $overrideDelivery !== null,
            ];
        });

        return view('deliveries.index', [
            'weeklyDeliveries' => $weeklyDeliveries,
            'focusedDeliveryId' => (int) $request->integer('focus'),
        ]);
    }

    public function show(Request $request, Delivery $delivery): RedirectResponse
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        $baseDelivery = $delivery->is_weekly_override ? $delivery->overridesDelivery : $delivery;

        abort_unless($baseDelivery instanceof Delivery, 404);

        return redirect()->route('app.deliveries.index', [
            'focus' => $baseDelivery->id,
        ]);
    }

    public function gift(Request $request, Delivery $delivery): View
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        $nextWeek = Carbon::parse($delivery->scheduled_for_date)->addWeek();
        $suggestedTargetDate = $nextWeek->isPast() ? Carbon::tomorrow()->toDateString() : $nextWeek->toDateString();

        return view('deliveries.gift', [
            'delivery' => $delivery,
            'suggestedTargetDate' => $suggestedTargetDate,
        ]);
    }

    public function storeGift(GiftDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        $baseDelivery = $delivery->is_weekly_override ? $delivery->overridesDelivery : $delivery;
        abort_unless($baseDelivery instanceof Delivery, 404);

        if ($baseDelivery->scheduled_for_date->toDateString() < now()->toDateString()) {
            return redirect()
                ->route('app.deliveries.gift', $baseDelivery)
                ->withErrors(['target_date' => __('The delivery date is in the past.')]);
        }

        $effectiveDelivery = $baseDelivery->overrideDeliveries()->latest()->first() ?? $baseDelivery;
        if ($effectiveDelivery->status === DeliveryStatus::Delivered) {
            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-gift-already-delivered');
        }

        $validated = $request->validated();
        $targetDate = $validated['target_date'];

        $giftAddress = $request->user()->addresses()->create([
            'label' => __('Geschenk an :name', ['name' => $validated['recipient_name']]),
            'recipient_name' => $validated['recipient_name'],
            'street_line_1' => $validated['street_line_1'],
            'street_line_2' => $validated['street_line_2'] ?? null,
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'country_code' => strtoupper($validated['country_code']),
            'phone' => $validated['phone'] ?? null,
            'delivery_instructions' => $validated['delivery_instructions'] ?? null,
            'is_primary' => false,
        ]);

        $existingOverride = $baseDelivery->overrideDeliveries()->latest()->first();
        if (! $existingOverride instanceof Delivery) {
            Delivery::query()->create([
                'user_id' => $baseDelivery->user_id,
                'address_id' => $baseDelivery->address_id,
                'scheduled_for_date' => $baseDelivery->scheduled_for_date->toDateString(),
                'timeslot' => $baseDelivery->timeslot,
                'status' => DeliveryStatus::Skipped,
                'special_instructions' => __('Gifted to :name', ['name' => $validated['recipient_name']]),
                'is_weekly_override' => true,
                'overrides_delivery_id' => $baseDelivery->id,
            ]);
        } else {
            $existingOverride->update([
                'status' => DeliveryStatus::Skipped,
                'special_instructions' => trim(
                    ($existingOverride->special_instructions ? $existingOverride->special_instructions."\n" : '')
                    .__('Gifted to :name', ['name' => $validated['recipient_name']])
                ),
            ]);
        }

        $giftDelivery = Delivery::query()->create([
            'user_id' => $baseDelivery->user_id,
            'address_id' => $giftAddress->id,
            'scheduled_for_date' => $targetDate,
            'timeslot' => $baseDelivery->timeslot,
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => __('Gift delivery for :name', ['name' => $validated['recipient_name']]),
            'is_weekly_override' => false,
        ]);

        return redirect()
            ->route('app.deliveries.index', ['focus' => $giftDelivery->id])
            ->with('status', 'delivery-gifted');
    }
}
