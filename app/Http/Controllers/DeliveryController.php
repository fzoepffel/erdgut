<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Http\Requests\DeliveryWeeklyOverrideRequest;
use App\Models\Delivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function gift(Request $request, Delivery $delivery): View
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        return view('deliveries.gift', [
            'delivery' => $delivery,
        ]);
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
