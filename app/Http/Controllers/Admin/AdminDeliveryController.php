<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminDeliveriesIndexRequest;
use App\Models\Delivery;
use Illuminate\Contracts\View\View;

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
}
