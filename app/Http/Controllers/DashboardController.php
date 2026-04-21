<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Support\SubscriberAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('app.admin.dashboard');
        }

        $user = $request->user();

        // Trial-only dashboard: user has a paid/scheduled/delivered trial order, no active subscription.
        // Pending-payment orders are intentionally excluded — they represent an in-progress or
        // abandoned onboarding attempt and must not surface as a real trial experience.
        if (SubscriberAccess::hasTrialOrderOnly($user)) {
            $trialOrder = Order::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    OrderStatus::Paid->value,
                    OrderStatus::Scheduled->value,
                    OrderStatus::Delivered->value,
                ])
                ->with(['address', 'box.items.product'])
                ->latest()
                ->first();

            return view('dashboard-trial', [
                'order' => $trialOrder,
            ]);
        }

        if (SubscriberAccess::shouldShowWelcomeDashboard($user)) {
            return view('dashboard-welcome');
        }

        $userId = $user->id;
        $today = now()->toDateString();

        $upcomingDeliveries = Delivery::query()
            ->where('user_id', $userId)
            ->where('is_weekly_override', false)
            ->where('scheduled_for_date', '>=', $today)
            ->with([
                'address',
                'overrideDeliveries' => fn ($query) => $query->latest(),
                'overrideDeliveries.address',
            ])
            ->orderBy('scheduled_for_date')
            ->limit(12)
            ->get();

        $deliveryCards = $upcomingDeliveries->map(function (Delivery $delivery) use ($userId): ?array {
            $effectiveDelivery = $delivery->overrideDeliveries->first() ?? $delivery;

            // Hide only standalone skipped base rows (same rule as before). When a weekly
            // override exists—e.g. gift flow marks the override as skipped—the base row may
            // still read "scheduled" but the effective row is skipped; we must still show
            // that week so the dashboard matches the deliveries timeline.
            if ($effectiveDelivery->status === DeliveryStatus::Skipped && $delivery->overrideDeliveries->isEmpty()) {
                return null;
            }

            $weekStart = $effectiveDelivery->scheduled_for_date->copy()->startOfWeek()->toDateString();
            $box = Box::query()
                ->where('user_id', $userId)
                ->whereDate('week_start_date', $weekStart)
                ->first();
            $deadline = $box?->modification_deadline_at;
            $canModify = $box?->status === BoxStatus::Editable && $deadline?->isFuture();

            return [
                'delivery' => $effectiveDelivery,
                'box' => $box,
                'deadline' => $deadline,
                'canModifyBox' => $canModify,
            ];
        })->filter()->values()->take(4);

        $upcomingBoxes = Box::query()
            ->where('user_id', $userId)
            ->where('week_start_date', '>=', $today)
            ->orderBy('week_start_date')
            ->limit(3)
            ->get();

        $currentBox = Box::query()
            ->where('user_id', $userId)
            ->whereDate('week_start_date', '<=', $today)
            ->whereDate('week_start_date', '>=', now()->subDays(6)->toDateString())
            ->with('items.product')
            ->orderByDesc('week_start_date')
            ->first();

        $primarySubscription = Subscription::query()
            ->where('user_id', $userId)
            ->where('status', '!=', SubscriptionStatus::Cancelled)
            ->orderByDesc('updated_at')
            ->first();

        $needsPaymentSetup = $primarySubscription !== null
            && $primarySubscription->status === SubscriptionStatus::Active
            && empty($primarySubscription->sumup_token);

        // Small trial strip when a subscriber also has an open trial order (e.g. trial first, then subscribed).
        // PendingPayment intentionally excluded — only show the strip for confirmed trials.
        $openTrialOrder = Order::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Scheduled->value,
            ])
            ->latest()
            ->first();

        return view('dashboard', [
            'deliveryCards' => $deliveryCards,
            'upcomingBoxes' => $upcomingBoxes,
            'currentBox' => $currentBox,
            'primarySubscription' => $primarySubscription,
            'needsPaymentSetup' => $needsPaymentSetup,
            'openTrialOrder' => $openTrialOrder,
        ]);
    }
}
