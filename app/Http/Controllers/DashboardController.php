<?php

namespace App\Http\Controllers;

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

        $upcomingDeliveries = Delivery::query()
            ->where('user_id', $userId)
            ->where('scheduled_for_date', '>=', now()->toDateString())
            ->where('status', DeliveryStatus::Scheduled)
            ->with('address')
            ->orderBy('scheduled_for_date')
            ->limit(4)
            ->get();

        $upcomingBoxes = Box::query()
            ->where('user_id', $userId)
            ->where('week_start_date', '>=', now()->toDateString())
            ->orderBy('week_start_date')
            ->limit(3)
            ->get();

        return view('dashboard', [
            'upcomingDeliveries' => $upcomingDeliveries,
            'upcomingBoxes' => $upcomingBoxes,
        ]);
    }
}
