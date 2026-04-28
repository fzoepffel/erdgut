<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUsersIndexRequest;
use App\Http\Requests\Admin\AdminUsersStoreAdminRequest;
use App\Models\AuditEvent;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(AdminUsersIndexRequest $request): View
    {
        $this->authorize('admin');

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = User::query()->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at']);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where('email', 'like', '%'.$escaped.'%');
        }

        $users = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.operations.users.index', [
            'users' => $users,
            'adminCount' => (int) User::query()->where('is_admin', true)->count(),
        ]);
    }

    /**
     * Customer detail page — pulls subscription, addresses, trial orders,
     * boxes, deliveries, payments, and the audit timeline tied to this user
     * onto a single screen. Replaces the "filter and hop" workflow that
     * required walking Abos → Lieferungen → Zahlungen → Box-Setup to triage
     * one customer.
     *
     * Linked from three entry points: Nutzer index, Abos index, and the
     * Bestellungen index (when filtered to trial_box).
     */
    public function show(User $user): View
    {
        $this->authorize('admin');

        $user->load([
            'addresses' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'deliveryPreference',
            'subscriptions' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $primarySubscription = $user->subscriptions->first();

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $boxes = Box::query()
            ->where('user_id', $user->id)
            ->with('items.product')
            ->orderByDesc('week_start_date')
            ->limit(8)
            ->get();

        $deliveries = Delivery::query()
            ->where('user_id', $user->id)
            ->with('address')
            ->orderByDesc('scheduled_for_date')
            ->limit(10)
            ->get();

        $payments = Payment::query()
            ->whereHas('subscription', fn ($q) => $q->where('user_id', $user->id))
            ->orWhere(fn ($q) => $q->whereHas('order', fn ($q2) => $q2->where('user_id', $user->id)))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Audit timeline: events where this user is the *actor* OR where
        // the event targets one of this user's subscriptions / orders /
        // deliveries / boxes / payments. Cap at 30 for performance.
        $subscriptionIds = $user->subscriptions->pluck('id')->all();
        $orderIds = $orders->pluck('id')->all();
        $deliveryIds = $deliveries->pluck('id')->all();
        $boxIds = $boxes->pluck('id')->all();
        $paymentIds = $payments->pluck('id')->all();

        $auditEvents = AuditEvent::query()
            ->with('actor:id,email')
            ->where(function ($q) use ($user, $subscriptionIds, $orderIds, $deliveryIds, $boxIds, $paymentIds): void {
                $q->where('actor_user_id', $user->id);

                if (! empty($subscriptionIds)) {
                    $q->orWhere(fn ($qq) => $qq->where('entity_type', 'subscription')->whereIn('entity_id', $subscriptionIds));
                }
                if (! empty($orderIds)) {
                    $q->orWhere(fn ($qq) => $qq->where('entity_type', 'order')->whereIn('entity_id', $orderIds));
                }
                if (! empty($deliveryIds)) {
                    $q->orWhere(fn ($qq) => $qq->where('entity_type', 'delivery')->whereIn('entity_id', $deliveryIds));
                }
                if (! empty($boxIds)) {
                    $q->orWhere(fn ($qq) => $qq->where('entity_type', 'box')->whereIn('entity_id', $boxIds));
                }
                if (! empty($paymentIds)) {
                    $q->orWhere(fn ($qq) => $qq->where('entity_type', 'payment')->whereIn('entity_id', $paymentIds));
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('admin.operations.users.show', [
            'user' => $user,
            'primarySubscription' => $primarySubscription,
            'orders' => $orders,
            'boxes' => $boxes,
            'deliveries' => $deliveries,
            'payments' => $payments,
            'auditEvents' => $auditEvents,
        ]);
    }

    public function storeAdmin(AdminUsersStoreAdminRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validated();

        /** @var User $user */
        $user = DB::transaction(function () use ($validated): User {
            $u = User::create([
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'password' => (string) $validated['password'],
                'is_test' => true,
            ]);
            $u->forceFill(['is_admin' => true])->save();

            return $u;
        });

        return redirect()
            ->route('app.admin.users.index')
            ->with('status', __('admin.users.flash_admin_created', ['email' => $user->email]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('admin');

        $current = request()->user();
        if ($current && (int) $current->id === (int) $user->id) {
            return back()->with('status', __('admin.users.flash_cannot_delete_self'));
        }

        if ((bool) $user->is_admin) {
            $adminCount = (int) User::query()->where('is_admin', true)->count();
            if ($adminCount <= 1) {
                return back()->with('status', __('admin.users.flash_cannot_delete_last_admin'));
            }
        }

        $user->delete();

        return redirect()
            ->route('app.admin.users.index')
            ->with('status', __('admin.users.flash_deleted'));
    }

    public function setRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);
        $promote = $data['role'] === 'admin';

        $current = $request->user();
        if ($current && (int) $current->id === (int) $user->id) {
            return back()->with('status', __('admin.users.flash_cannot_change_self_role'));
        }

        if (! $promote && (bool) $user->is_admin) {
            $adminCount = (int) User::query()->where('is_admin', true)->count();
            if ($adminCount <= 1) {
                return back()->with('status', __('admin.users.flash_cannot_demote_last_admin'));
            }
        }

        $user->forceFill(['is_admin' => $promote])->save();

        $flashKey = $promote ? 'admin.users.flash_promoted' : 'admin.users.flash_demoted';

        return back()->with('status', __($flashKey, ['email' => $user->email]));
    }
}
