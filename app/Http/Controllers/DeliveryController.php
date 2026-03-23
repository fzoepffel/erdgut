<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Http\Requests\DeliveryWeeklyOverrideRequest;
use App\Http\Requests\GiftDeliveryRequest;
use App\Models\Delivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

        $addresses = $user->addresses()
            ->orderByDesc('is_primary')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return view('deliveries.index', [
            'weeklyDeliveries' => $weeklyDeliveries,
            'addresses' => $addresses,
            'timeslotOptions' => $this->timeslotOptions(),
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

    public function updateOverride(
        DeliveryWeeklyOverrideRequest $request,
        Delivery $delivery
    ): RedirectResponse {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        $baseDelivery = $delivery->is_weekly_override ? $delivery->overridesDelivery : $delivery;

        abort_unless($baseDelivery instanceof Delivery, 404);

        $validated = $request->validated();
        $skipWeek = (bool) ($validated['skip_week'] ?? false);
        $resetOverride = (bool) ($validated['reset_override'] ?? false);
        $existingOverride = $baseDelivery->overrideDeliveries()->latest()->first();
        $effectiveDelivery = $existingOverride ?? $baseDelivery;

        if ($resetOverride) {
            if ($existingOverride instanceof Delivery) {
                $existingOverride->delete();
            }

            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-weekly-override-reset');
        }

        if (! $this->isOverrideEditable($baseDelivery, $effectiveDelivery)) {
            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-weekly-override-not-allowed');
        }

        if ($skipWeek && ! $this->canSkipWeek($baseDelivery, $effectiveDelivery)) {
            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-weekly-skip-not-allowed');
        }

        $payload = [
            'user_id' => $baseDelivery->user_id,
            'address_id' => (int) ($validated['address_id'] ?? $baseDelivery->address_id),
            'scheduled_for_date' => $baseDelivery->scheduled_for_date->toDateString(),
            'timeslot' => $validated['timeslot'] ?? $baseDelivery->timeslot,
            'status' => $skipWeek ? DeliveryStatus::Skipped : DeliveryStatus::Scheduled,
            'special_instructions' => array_key_exists('special_instructions', $validated)
                ? ($validated['special_instructions'] ?: null)
                : $baseDelivery->special_instructions,
            'is_weekly_override' => true,
            'overrides_delivery_id' => $baseDelivery->id,
        ];

        $matchesBaseDefaults =
            $payload['address_id'] === (int) $baseDelivery->address_id
            && $payload['timeslot'] === $baseDelivery->timeslot
            && $payload['status'] === $baseDelivery->status
            && $payload['special_instructions'] === $baseDelivery->special_instructions;

        if ($matchesBaseDefaults) {
            if ($existingOverride instanceof Delivery) {
                $existingOverride->delete();
            }

            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-weekly-override-reset');
        }

        if ($existingOverride instanceof Delivery) {
            $existingOverride->update($payload);
        } else {
            Delivery::query()->create($payload);
        }

        return redirect()
            ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
            ->with('status', 'delivery-weekly-override-updated');
    }

    public function skip(Request $request, Delivery $delivery): RedirectResponse
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        return redirect()
            ->route('app.dashboard')
            ->with('status', 'skip-week-coming-soon');
    }

    public function postpone(Request $request, Delivery $delivery): RedirectResponse
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        $baseDelivery = $delivery->is_weekly_override ? $delivery->overridesDelivery : $delivery;
        abort_unless($baseDelivery instanceof Delivery, 404);

        if ($baseDelivery->scheduled_for_date->toDateString() < now()->toDateString()) {
            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-postpone-past');
        }

        $effectiveDelivery = $baseDelivery->overrideDeliveries()->latest()->first() ?? $baseDelivery;
        if ($effectiveDelivery->status === DeliveryStatus::Delivered) {
            return redirect()
                ->route('app.deliveries.index', ['focus' => $baseDelivery->id])
                ->with('status', 'delivery-postpone-already-delivered');
        }

        $existingOverride = $baseDelivery->overrideDeliveries()->latest()->first();
        if (! $existingOverride instanceof Delivery) {
            Delivery::query()->create([
                'user_id' => $baseDelivery->user_id,
                'address_id' => $baseDelivery->address_id,
                'scheduled_for_date' => $baseDelivery->scheduled_for_date->toDateString(),
                'timeslot' => $baseDelivery->timeslot,
                'status' => DeliveryStatus::Skipped,
                'special_instructions' => $baseDelivery->special_instructions,
                'is_weekly_override' => true,
                'overrides_delivery_id' => $baseDelivery->id,
            ]);
        } else {
            $existingOverride->update(['status' => DeliveryStatus::Skipped]);
        }

        $nextWeekDate = Carbon::parse($baseDelivery->scheduled_for_date)->addWeek();
        $nextDelivery = Delivery::query()->firstOrCreate(
            [
                'user_id' => $baseDelivery->user_id,
                'scheduled_for_date' => $nextWeekDate->toDateString(),
                'is_weekly_override' => false,
            ],
            [
                'address_id' => $baseDelivery->address_id,
                'timeslot' => $baseDelivery->timeslot,
                'status' => DeliveryStatus::Scheduled,
                'special_instructions' => $baseDelivery->special_instructions,
            ]
        );

        return redirect()
            ->route('app.deliveries.index', ['focus' => $nextDelivery->id])
            ->with('status', 'delivery-postponed');
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
                ->withErrors(['target_date' => __('Die Lieferung liegt in der Vergangenheit.')]);
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
                'special_instructions' => __('Geschenkt an :name', ['name' => $validated['recipient_name']]),
                'is_weekly_override' => true,
                'overrides_delivery_id' => $baseDelivery->id,
            ]);
        } else {
            $existingOverride->update([
                'status' => DeliveryStatus::Skipped,
                'special_instructions' => trim(
                    ($existingOverride->special_instructions ? $existingOverride->special_instructions."\n" : '')
                    .__('Geschenkt an :name', ['name' => $validated['recipient_name']])
                ),
            ]);
        }

        $giftDelivery = Delivery::query()->create([
            'user_id' => $baseDelivery->user_id,
            'address_id' => $giftAddress->id,
            'scheduled_for_date' => $targetDate,
            'timeslot' => $baseDelivery->timeslot,
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => __('Geschenklieferung an :name', ['name' => $validated['recipient_name']]),
            'is_weekly_override' => false,
        ]);

        return redirect()
            ->route('app.deliveries.index', ['focus' => $giftDelivery->id])
            ->with('status', 'delivery-gifted');
    }

    /**
     * @return Collection<int, string>
     */
    private function timeslotOptions(): Collection
    {
        return collect(Delivery::allowedTimeslots());
    }

    private function isOverrideEditable(Delivery $baseDelivery, Delivery $effectiveDelivery): bool
    {
        return $baseDelivery->scheduled_for_date->toDateString() >= now()->toDateString()
            && $effectiveDelivery->status !== DeliveryStatus::Delivered;
    }

    private function canSkipWeek(Delivery $baseDelivery, Delivery $effectiveDelivery): bool
    {
        if ($baseDelivery->scheduled_for_date->toDateString() < now()->toDateString()) {
            return false;
        }

        return in_array($effectiveDelivery->status, [
            DeliveryStatus::Scheduled,
            DeliveryStatus::Skipped,
        ], true);
    }
}
