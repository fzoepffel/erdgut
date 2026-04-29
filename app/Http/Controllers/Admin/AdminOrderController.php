<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\Timeslot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminOrderStoreRequest;
use App\Mail\TrialDeliveryScheduledMail;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\Onboarding\TrialOrderService;
use App\Support\AuditLogger;
use App\Support\BoxCompositionService;
use App\Support\DeliveryProjector;
use App\Support\DeliveryZoneResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $status = $request->string('status')->toString();
        $productType = $request->string('product_type')->toString();

        $query = Order::query()
            ->with(['user:id,email,name', 'address:id,street_line_1,postal_code,city'])
            ->latest();

        if ($productType !== '' && in_array($productType, array_column(OrderProductType::cases(), 'value'), true)) {
            $query->where('product_type', $productType);
        }

        if ($status !== '' && in_array($status, array_column(OrderStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($request->boolean('needs_schedule')) {
            $query->whereNull('scheduled_for_date');
        }

        if ($request->boolean('needs_box')) {
            $query->whereNull('box_id');
        }

        if ($request->boolean('ready_to_deliver')) {
            $query->whereNotNull('box_id');
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'productType' => $productType,
            'statuses' => OrderStatus::cases(),
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

        $addresses = $selectedUserId > 0
            ? Address::query()
                ->where('user_id', $selectedUserId)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get()
            : collect();

        $zoneKey = $zones->resolveZoneKey($selectedUserId > 0 ? $selectedUserId : null);

        return view('admin.orders.create', [
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'addresses' => $addresses,
            'allowedTimeslots' => $zones->allowedTimeslots($zoneKey),
            'timeslotCases' => Timeslot::cases(),
        ]);
    }

    public function store(AdminOrderStoreRequest $request, TrialOrderService $trialOrders): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validated();
        $userId = (int) $data['user_id'];
        $user = User::query()->findOrFail($userId);

        $order = $trialOrders->createOrder($user, [
            'address_id' => (int) $data['address_id'],
            'box_size' => (string) $data['box_size'],
            'preferences' => [
                'veg_only' => (bool) ($data['preferences']['veg_only'] ?? false),
                'meat_add_on' => (bool) ($data['preferences']['meat_add_on'] ?? false),
                'eggs_dairy_add_on' => (bool) ($data['preferences']['eggs_dairy_add_on'] ?? false),
            ],
            'allergies' => array_values((array) ($data['allergies'] ?? [])),
            'allergies_note' => $data['allergies_note'] ?? null,
            'preferred_timeslot' => $data['preferred_timeslot'] ?? null,
            'preferred_delivery_window' => $data['preferred_delivery_window'] ?? null,
            'special_instructions' => $data['special_instructions'] ?? null,
            'is_test' => false,
        ]);

        AuditLogger::log(
            entityType: 'order',
            entityId: $order->id,
            action: 'admin_create',
            toState: $order->status->value,
            reason: $data['reason'] ?? null,
            metadata: [
                'product_type' => $order->product_type->value,
                'box_size' => (string) $order->box_size,
                'amount' => (string) $order->amount,
                'currency' => $order->currency,
                'order_user_id' => $order->user_id,
            ],
            actorUserId: $request->user()?->id,
        );

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'admin-order-created');
    }

    public function show(Order $order): View
    {
        $this->authorize('admin');

        $order->load([
            'user',
            'address',
            'deliveries.address',
            'payments' => fn ($q) => $q->latest(),
        ]);

        // Trial-box composition is owned by the unified Box-Setup view, so
        // this page only deep-links there. The order has to be scheduled
        // first (i.e. carry a `scheduled_for_date`) so we know which week's
        // Box-Setup to open.
        $boxSetupUrl = null;
        if ($order->product_type === OrderProductType::TrialBox && $order->scheduled_for_date !== null) {
            $monday = $order->scheduled_for_date->copy()->startOfWeek(Carbon::MONDAY);
            $boxSetupUrl = route('app.admin.boxes.setup.show', [
                'week' => $monday->toDateString(),
            ]);
        }

        return view('admin.orders.show', [
            'order' => $order,
            'timeslots' => Delivery::allowedTimeslots(),
            'boxSetupUrl' => $boxSetupUrl,
        ]);
    }

    public function schedule(Request $request, Order $order, DeliveryProjector $projector): RedirectResponse
    {
        $this->authorize('admin');

        // Trial-box composition lives in Box-Setup, so scheduling now happens
        // BEFORE the box is composed (it's the gateway that pins the trial
        // order to a delivery week). No "needs box first" gate.

        $validated = $request->validate([
            'scheduled_for_date' => ['required', 'date', 'after_or_equal:today'],
            'timeslot' => ['required', 'string', 'in:'.implode(',', Delivery::allowedTimeslots())],
        ]);

        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Refunded) {
            return back()->with('status', 'order-not-schedulable');
        }

        $date = Carbon::parse($validated['scheduled_for_date']);

        DB::transaction(function () use ($order, $date, $validated, $projector) {
            $order->markScheduled($date, $validated['timeslot']);
            $projector->projectForTrialOrder($order->fresh(), $date, $validated['timeslot']);
        });

        if ($order->user) {
            Mail::to($order->user->email)->queue(new TrialDeliveryScheduledMail($order->fresh()));
        }

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'order-scheduled');
    }

    public function compose(Request $request, Order $order, BoxCompositionService $composer): RedirectResponse
    {
        $this->authorize('admin');

        if ($order->product_type === OrderProductType::TrialBox) {
            return redirect()
                ->route('app.admin.orders.show', $order)
                ->with('status', __('admin.trial_order_box_setup.flash_redirected_from_compose'));
        }

        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Refunded) {
            return back()->with('status', 'order-not-composable');
        }

        $box = $order->box;
        if (! $box) {
            $box = Box::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'week_start_date' => ($order->scheduled_for_date ?? now())->copy()->startOfWeek()->toDateString(),
                'status' => BoxStatus::Locked,
                'modification_deadline_at' => now(),
                'estimated_value' => 0,
                'is_test' => (bool) $order->is_test,
            ]);
            $order->forceFill(['box_id' => $box->id])->save();
        }

        $composer->composePresetFromScratch($box, (string) $order->box_size);

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'order-composed');
    }

    public function markDelivered(Order $order): RedirectResponse
    {
        $this->authorize('admin');

        DB::transaction(function () use ($order) {
            $order->markDelivered();
            $order->deliveries()->update([
                'status' => DeliveryStatus::Delivered,
            ]);
        });

        return redirect()
            ->route('app.admin.orders.show', $order)
            ->with('status', 'order-delivered');
    }
}
