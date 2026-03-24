<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSubscriptionsIndexRequest;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;

class AdminSubscriptionController extends Controller
{
    public function index(AdminSubscriptionsIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = Subscription::query()
            ->select([
                'id',
                'user_id',
                'plan',
                'box_size',
                'status',
                'next_charge_date',
                'pause_from',
                'pause_until',
                'created_at',
            ])
            ->with(['user:id,email']);

        if (($validated['missing_payment'] ?? null) === '1') {
            $query->where('status', SubscriptionStatus::Active)
                ->whereNull('sumup_token');
        }

        if (($validated['paused_exhausted'] ?? null) === '1') {
            $maxRetries = (int) config('payments.max_retries', 3);
            $query->where('status', SubscriptionStatus::Paused)
                ->whereHas('payments', function ($pq) use ($maxRetries): void {
                    $pq->where('status', Payment::STATUS_FAILED)
                        ->where('attempt_number', '>=', $maxRetries);
                });
        }

        $subscriptions = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.operations.subscriptions.index', [
            'subscriptions' => $subscriptions,
        ]);
    }
}
