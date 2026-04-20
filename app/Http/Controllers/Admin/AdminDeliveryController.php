<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminDeliveriesIndexRequest;
use App\Http\Requests\Admin\AdminDeliveriesSyncFromBoxesRequest;
use App\Http\Requests\Admin\AdminDeliveryStoreRequest;
use App\Http\Requests\Admin\AdminDeliveryUpdateRequest;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use App\Services\DeliveriesFromBoxesSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
                'created_at',
            ])
            ->with([
                'user:id,email',
                'address:id,street_line_1,postal_code,city',
            ]);

        if (! empty($validated['status'])) {
            $query->where('status', DeliveryStatus::from($validated['status']));
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

        $delivery->update($request->deliveryAttributes());

        return redirect()
            ->route('app.admin.deliveries.index')
            ->with('status', 'admin-delivery-updated');
    }

    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_map(fn (DeliveryStatus $s) => $s->value, DeliveryStatus::cases()))],
        ]);

        $delivery->update([
            'status' => DeliveryStatus::from($validated['status']),
        ]);

        return redirect()
            ->back()
            ->with('status', 'admin-delivery-status-updated');
    }
}
