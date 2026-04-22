<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPaymentsIndexRequest;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AdminPaymentController extends Controller
{
    public function index(AdminPaymentsIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);
        $dateField = $validated['date_field'] ?? 'created_at';
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $kind = $validated['kind'] ?? null;

        $query = Payment::query()
            ->select([
                'id',
                'subscription_id',
                'order_id',
                'amount',
                'currency',
                'status',
                'attempt_number',
                'failure_reason',
                'paid_at',
                'failed_at',
                'created_at',
            ])
            ->with([
                'subscription:id,user_id,plan,status',
                'subscription.user:id,email',
                'order:id,user_id',
                'order.user:id,email',
            ]);

        if ($kind === 'subscription') {
            $query->whereNotNull('subscription_id');
        } elseif ($kind === 'order') {
            $query->whereNotNull('order_id');
        }

        if (! empty($validated['subscription_id'])) {
            $query->where('subscription_id', (int) $validated['subscription_id']);
        }

        if (! empty($validated['order_id'])) {
            $query->where('order_id', (int) $validated['order_id']);
        }

        if (($validated['stale_pending'] ?? null) === '1') {
            $hours = (int) config('admin.pending_payment_stale_hours', 48);
            $query->where('status', Payment::STATUS_PENDING)
                ->where('created_at', '<', Carbon::now()->subHours($hours));
        } elseif (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate($dateField, '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate($dateField, '<=', $validated['date_to']);
        }

        $query->orderBy($sort, $direction)->orderBy('id', $direction);

        $payments = $query->paginate($perPage)->withQueryString();

        return view('admin.operations.payments.index', [
            'payments' => $payments,
            'dateField' => $dateField,
            'kind' => $kind,
        ]);
    }
}
