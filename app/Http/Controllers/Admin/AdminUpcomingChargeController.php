<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpcomingChargesIndexRequest;
use App\Models\Subscription;
use App\Services\Admin\UpcomingBillableSubscriptions;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AdminUpcomingChargeController extends Controller
{
    public function __construct(
        private readonly UpcomingBillableSubscriptions $upcomingBillable
    ) {}

    public function index(AdminUpcomingChargesIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);

        $today = Carbon::now()->startOfDay();
        $days = (int) config('admin.upcoming_charges_days', 30);
        $end = $today->copy()->addDays($days);

        $billable = $this->upcomingBillable->billableBetween($today, $end);
        $ids = $billable->pluck('id')->all();

        $subscriptions = collect();
        if ($ids !== []) {
            $subscriptions = Subscription::query()
                ->whereIn('id', $ids)
                ->select([
                    'id',
                    'user_id',
                    'plan',
                    'box_size',
                    'status',
                    'next_charge_date',
                    'pause_from',
                    'pause_until',
                ])
                ->with(['user:id,email'])
                ->get()
                ->sortBy(fn (Subscription $s) => $s->next_charge_date?->timestamp ?? 0)
                ->values();
        }

        $page = max(1, (int) $request->input('page', 1));
        $total = $subscriptions->count();
        $slice = $subscriptions->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.operations.upcoming.index', [
            'subscriptions' => $paginator,
            'windowDays' => $days,
        ]);
    }
}
