<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingMode;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Enums\Timeslot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSubscriptionsIndexRequest;
use App\Http\Requests\Admin\AdminSubscriptionStoreRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DeliveryZoneResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
                'preferences', // needed for expectedChargeAmount → eggs add-on detection
                'next_charge_date',
                'pause_from',
                'pause_until',
                'created_at',
            ])
            ->with(['user:id,email']);

        $query->whereHas('user', fn ($uq) => $uq->where('is_admin', false));

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

    public function create(Request $request, DeliveryZoneResolver $zones): View
    {
        $this->authorize('admin');

        $users = User::query()
            ->where('is_admin', false)
            ->orderBy('email')
            ->get(['id', 'email']);

        $selectedUserId = $request->integer('user_id') ?: (int) old('user_id', 0);
        $zoneKey = $zones->resolveZoneKey($selectedUserId > 0 ? $selectedUserId : null);

        return view('admin.operations.subscriptions.create', [
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'billingModes' => BillingMode::cases(),
            'plans' => SubscriptionPlan::cases(),
            'fulfillmentModes' => FulfillmentMode::cases(),
            'allowedWeekdays' => $zones->allowedWeekdays($zoneKey),
            'allowedTimeslots' => $zones->allowedTimeslots($zoneKey),
            'timeslotCases' => Timeslot::cases(),
        ]);
    }

    public function store(AdminSubscriptionStoreRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validated();
        $billingMode = BillingMode::from($data['billing_mode']);

        $subscription = Subscription::query()->create([
            'user_id' => (int) $data['user_id'],
            'plan' => $data['plan'],
            'box_size' => $data['box_size'],
            'status' => SubscriptionStatus::Active,
            'billing_mode' => $billingMode,
            'fulfillment_mode' => FulfillmentMode::from($data['fulfillment_mode']),
            'delivery_weekday' => $data['delivery_weekday'] ?? null,
            'preferred_timeslot' => $data['preferred_timeslot'] ?? null,
            'preferences' => [
                'veg_only' => (bool) ($data['preferences']['veg_only'] ?? false),
                'meat_add_on' => (bool) ($data['preferences']['meat_add_on'] ?? false),
                'eggs_dairy_add_on' => (bool) ($data['preferences']['eggs_dairy_add_on'] ?? false),
            ],
            'allergies' => [],
            'is_test' => false,
            'trial_completed_at' => now(),
        ]);

        AuditLogger::log(
            entityType: 'subscription',
            entityId: $subscription->id,
            action: 'admin_create',
            toState: SubscriptionStatus::Active->value,
            reason: $data['reason'] ?? null,
            metadata: [
                'billing_mode' => $billingMode->value,
                'plan' => $data['plan'],
                'box_size' => $data['box_size'],
            ],
        );

        return redirect()
            ->route('app.admin.subscriptions.index')
            ->with('status', 'admin-subscription-created')
            ->with('admin_subscription_created_id', $subscription->id);
    }
}
