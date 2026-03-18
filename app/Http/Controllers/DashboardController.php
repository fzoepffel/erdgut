<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Models\Box;
use App\Models\Delivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $upcomingDeliveries = Delivery::query()
            ->where('user_id', $userId)
            ->where('scheduled_for_date', '>=', $today)
            ->where('status', DeliveryStatus::Scheduled)
            ->with('address')
            ->orderBy('scheduled_for_date')
            ->limit(4)
            ->get();

        $deliveryCards = $upcomingDeliveries->map(function (Delivery $delivery) use ($userId): array {
            $weekStart = $delivery->scheduled_for_date->copy()->startOfWeek()->toDateString();
            $box = Box::query()
                ->where('user_id', $userId)
                ->whereDate('week_start_date', $weekStart)
                ->first();
            $deadline = $box?->modification_deadline_at;
            $canModify = $box?->status === BoxStatus::Editable && $deadline?->isFuture();

            return [
                'delivery' => $delivery,
                'box' => $box,
                'deadline' => $deadline,
                'canModifyBox' => $canModify,
            ];
        });

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

        return view('dashboard', [
            'deliveryCards' => $deliveryCards,
            'upcomingBoxes' => $upcomingBoxes,
            'currentBox' => $currentBox,
        ]);
    }
}
