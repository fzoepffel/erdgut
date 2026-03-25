<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Delivery;
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

        if (SubscriberAccess::shouldShowWelcomeDashboard($request->user())) {
            return view('dashboard-welcome');
        }

        $userId = $request->user()->id;
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

            if ($effectiveDelivery->status !== DeliveryStatus::Scheduled) {
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

        return view('dashboard', [
            'deliveryCards' => $deliveryCards,
            'upcomingBoxes' => $upcomingBoxes,
            'currentBox' => $currentBox,
            'primarySubscription' => $primarySubscription,
            'needsPaymentSetup' => $needsPaymentSetup,
        ]);
    }
}
