<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminDeliveriesBulkDelayRequest;
use App\Http\Requests\Admin\AdminDeliveriesIndexRequest;
use App\Http\Requests\Admin\AdminDeliveriesSyncFromBoxesRequest;
use App\Http\Requests\Admin\AdminDeliveryStoreRequest;
use App\Http\Requests\Admin\AdminDeliveryUpdateRequest;
use App\Mail\DeliveredMail;
use App\Mail\DeliveryInTransitMail;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use App\Services\DeliveriesFromBoxesSync;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminDeliveryController extends Controller
{
    public function index(AdminDeliveriesIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = Delivery::query()
            ->select([
                'id',
                'user_id',
                'address_id',
                'scheduled_for_date',
                'status',
                'timeslot',
                'is_weekly_override',
                'overrides_delivery_id',
                'order_id',
                'created_at',
            ])
            ->with([
                'user:id,email',
                'address:id,street_line_1,postal_code,city',
            ]);

        if (! empty($validated['status'])) {
            $query->where('status', DeliveryStatus::from($validated['status']));
        }

        if (($validated['trial_only'] ?? null) === '1') {
            $query->whereNotNull('order_id');
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('scheduled_for_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('scheduled_for_date', '<=', $validated['date_to']);
        }

        $deliveries = $query->orderByDesc('scheduled_for_date')->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.operations.deliveries.index', [
            'deliveries' => $deliveries,
            'deliveryStatuses' => DeliveryStatus::cases(),
        ]);
    }

    public function syncFromBoxes(AdminDeliveriesSyncFromBoxesRequest $request, DeliveriesFromBoxesSync $sync): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $from = ! empty($validated['from'] ?? null)
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->startOfWeek()->startOfDay();

        $count = $sync->run($from);

        return redirect()
            ->route('app.admin.deliveries.index')
            ->with('status', 'admin-deliveries-synced')
            ->with('admin_deliveries_sync_count', $count)
            ->with('admin_deliveries_sync_from', $from->toDateString());
    }

    public function create(Request $request): View
    {
        $this->authorize('admin');

        $users = User::query()->orderBy('email')->get(['id', 'email']);
        $selectedUserId = $request->integer('user_id') ?: (int) old('user_id', 0);
        $addresses = $selectedUserId > 0
            ? Address::query()->where('user_id', $selectedUserId)->orderBy('label')->get()
            : collect();

        return view('admin.operations.deliveries.create', [
            'users' => $users,
            'addresses' => $addresses,
            'selectedUserId' => $selectedUserId,
            'timeslots' => Delivery::allowedTimeslots(),
            'deliveryStatuses' => DeliveryStatus::cases(),
        ]);
    }

    public function store(AdminDeliveryStoreRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        Delivery::query()->create($request->deliveryAttributes());

        return redirect()
            ->route('app.admin.deliveries.index')
            ->with('status', 'admin-delivery-created');
    }

    public function edit(Delivery $delivery): View
    {
        $this->authorize('admin');

        $delivery->loadMissing('user:id,email');
        $addresses = Address::query()
            ->where('user_id', $delivery->user_id)
            ->orderBy('label')
            ->get();

        return view('admin.operations.deliveries.edit', [
            'delivery' => $delivery,
            'addresses' => $addresses,
            'timeslots' => Delivery::allowedTimeslots(),
            'deliveryStatuses' => DeliveryStatus::cases(),
        ]);
    }

    public function update(AdminDeliveryUpdateRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('admin');

        $attributes = $request->deliveryAttributes();
        $before = [
            'scheduled_for_date' => $delivery->scheduled_for_date?->toDateString(),
            'timeslot' => $delivery->timeslot,
            'address_id' => $delivery->address_id,
            'status' => $delivery->status?->value,
        ];

        $delivery->update($attributes);

        $after = [
            'scheduled_for_date' => $delivery->scheduled_for_date?->toDateString(),
            'timeslot' => $delivery->timeslot,
            'address_id' => $delivery->address_id,
            'status' => $delivery->status?->value,
        ];

        $rescheduled = $before['scheduled_for_date'] !== $after['scheduled_for_date']
            || $before['timeslot'] !== $after['timeslot'];

        // ERD-339: email the customer when an admin transitions the delivery into
        // an in-transit or delivered state (either is a notable event for them).
        if ($before['status'] !== $after['status']) {
            $recipient = $delivery->user?->email;
            if ($recipient !== null && $recipient !== '') {
                $fresh = $delivery->fresh() ?? $delivery;
                if ($after['status'] === DeliveryStatus::InTransit->value) {
                    Mail::to($recipient)->queue(new DeliveryInTransitMail($fresh));
                } elseif ($after['status'] === DeliveryStatus::Delivered->value) {
                    Mail::to($recipient)->queue(new DeliveredMail($fresh));
                }
            }
        }

        if ($rescheduled) {
            AuditLogger::log(
                entityType: 'delivery',
                entityId: $delivery->id,
                action: 'admin_reschedule',
                reason: $request->input('reason'),
                metadata: [
                    'before' => $before,
                    'after' => $after,
                    'cascades' => false,
                ],
            );
        } elseif ($before !== $after) {
            AuditLogger::log(
                entityType: 'delivery',
                entityId: $delivery->id,
                action: 'admin_update',
                fromState: $before['status'],
                toState: $after['status'],
                reason: $request->input('reason'),
                metadata: [
                    'before' => $before,
                    'after' => $after,
                ],
            );
        }

        return redirect()
            ->route('app.admin.deliveries.index')
            ->with('status', 'admin-delivery-updated');
    }

    public function bulkDelay(AdminDeliveriesBulkDelayRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validated();
        $date = Carbon::parse($data['date'])->startOfDay();
        $delta = (int) $data['delta_days'];
        $reason = $data['reason'] ?? null;

        $deliveries = Delivery::query()
            ->whereDate('scheduled_for_date', $date->toDateString())
            ->whereIn('status', [DeliveryStatus::Scheduled, DeliveryStatus::InTransit])
            ->get();

        foreach ($deliveries as $delivery) {
            $before = $delivery->scheduled_for_date?->toDateString();
            $after = Carbon::parse($before)->addDays($delta)->toDateString();

            $delivery->update(['scheduled_for_date' => $after]);

            AuditLogger::log(
                entityType: 'delivery',
                entityId: $delivery->id,
                action: 'admin_bulk_delay',
                reason: $reason,
                metadata: [
                    'before' => ['scheduled_for_date' => $before],
                    'after' => ['scheduled_for_date' => $after],
                    'delta_days' => $delta,
                    'bulk_source_date' => $date->toDateString(),
                ],
                actorUserId: $request->user()?->id,
            );
        }

        return redirect()
            ->route('app.admin.deliveries.index', ['date_from' => $date->toDateString(), 'date_to' => $date->copy()->addDays($delta + 7)->toDateString()])
            ->with('status', 'admin-deliveries-bulk-delayed')
            ->with('admin_deliveries_bulk_count', $deliveries->count());
    }

    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_map(fn (DeliveryStatus $s) => $s->value, DeliveryStatus::cases()))],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $previousStatus = $delivery->status?->value;
        $nextStatus = DeliveryStatus::from($validated['status']);

        $delivery->update([
            'status' => $nextStatus,
        ]);

        if ($previousStatus !== $nextStatus->value) {
            AuditLogger::log(
                entityType: 'delivery',
                entityId: $delivery->id,
                action: 'admin_status_change',
                fromState: $previousStatus,
                toState: $nextStatus->value,
                reason: $validated['reason'] ?? null,
            );

            // ERD-339: mirror the `update()` hook so quick status flips also
            // notify the customer when they're the kind of transition we email about.
            $recipient = $delivery->user?->email;
            if ($recipient !== null && $recipient !== '') {
                $fresh = $delivery->fresh() ?? $delivery;
                if ($nextStatus === DeliveryStatus::InTransit) {
                    Mail::to($recipient)->queue(new DeliveryInTransitMail($fresh));
                } elseif ($nextStatus === DeliveryStatus::Delivered) {
                    Mail::to($recipient)->queue(new DeliveredMail($fresh));
                }
            }
        }

        return redirect()
            ->back()
            ->with('status', 'admin-delivery-status-updated');
    }
}
