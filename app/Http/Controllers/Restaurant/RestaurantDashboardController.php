<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The verified-restaurant area home: next delivery day (+ what's in stock),
 * upcoming deliveries, active weekly deliveries, recent orders, invoices —
 * plus opportunity tiles that showcase what the B2B shop can do.
 */
class RestaurantDashboardController extends Controller
{
    public function index(Request $request, RestaurantCatalogService $catalog): View
    {
        $user = $request->user();

        // Next open delivery day + its in-stock product count (cached matrix —
        // the catalog page runs the same query).
        $nextDay = $catalog->selectableDates()->first();

        $upcoming = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [RestaurantOrderStatus::Submitted->value, RestaurantOrderStatus::Confirmed->value])
            ->whereDate('expected_delivery_date', '>=', today())
            ->withCount('items')
            ->orderBy('expected_delivery_date')
            ->limit(3)
            ->get();

        $standing = RestaurantStandingOrder::query()
            ->where('user_id', $user->id)
            ->active()
            ->with('items.product.translations')
            ->get();

        $recent = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->withCount('items')
            ->latest()
            ->limit(5)
            ->get();

        $invoices = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->whereNotNull('invoice_number')
            ->orderByDesc('invoiced_at')
            ->limit(5)
            ->get();

        return view('restaurant.dashboard', [
            'profile' => $user->restaurantProfile,
            'nextDay' => $nextDay,
            'upcoming' => $upcoming,
            'standing' => $standing,
            'recent' => $recent,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Shown to a logged-in restaurant whose account is still awaiting review.
     * Sits outside the verified-restaurant guard so it doesn't redirect-loop.
     */
    public function pending(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isRestaurant()) {
            return redirect()->route('app.dashboard');
        }

        if ($user->isVerifiedRestaurant()) {
            return redirect()->route('app.restaurant.dashboard');
        }

        return view('restaurant.pending', [
            'profile' => $user->restaurantProfile,
        ]);
    }
}
