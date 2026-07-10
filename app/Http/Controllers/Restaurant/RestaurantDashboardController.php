<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Restaurant\RestaurantCatalogService;
use App\Services\Shop\ShopClosureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        // Next ORDERABLE delivery day: like the shop's date picker, only days
        // that actually have stock count (an open day with 0 products is not
        // a delivery day from the restaurant's point of view).
        $nextDay = $catalog->selectableDates()
            ->first(fn (array $row): bool => $row['in_stock_count'] > 0);

        // "Diese Woche": the concrete deliveries of the current ISO week and
        // a preview of next week, with product images for the cards. These
        // are actual orders (weekly weeks are materialized), so no collapse.
        $closures = app(ShopClosureService::class);
        $weekOrders = function (Carbon $start, Carbon $end) use ($user) {
            return RestaurantOrder::query()
                ->where('user_id', $user->id)
                ->orders()
                ->whereIn('status', [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value])
                ->whereBetween('expected_delivery_date', [$start->toDateString(), $end->toDateString()])
                ->with(['items.product.translations', 'items.product.images'])
                ->orderBy('expected_delivery_date')
                ->get();
        };
        $thisWeek = $weekOrders(today()->startOfWeek(), today()->endOfWeek());
        $nextWeek = $weekOrders(today()->addWeek()->startOfWeek(), today()->addWeek()->endOfWeek());
        $weekLockState = $thisWeek->concat($nextWeek)->mapWithKeys(function (RestaurantOrder $o) use ($closures): array {
            $date = $o->expected_delivery_date->copy();
            $state = $closures->statesForRange($date, $date)[$date->toDateString()] ?? null;

            return [$o->id => [
                'locked' => $o->isLockedForChanges($closures),
                'cutoff' => $state['cutoff_label'] ?? null,
            ]];
        });

        // Stat only — the concrete deliveries live in the Diese/Nächste Woche
        // blocks above (the old "Anstehende Lieferungen" card duplicated them).
        $upcomingCount = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->orders()
            ->whereIn('status', [RestaurantOrderStatus::Submitted->value, RestaurantOrderStatus::Confirmed->value])
            ->whereDate('expected_delivery_date', '>=', today())
            ->count();

        // Requests awaiting the Erdgut team (kind=request, still submitted).
        $openRequests = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->requests()
            ->where('status', RestaurantOrderStatus::Submitted->value)
            ->withCount('items')
            ->latest()
            ->limit(3)
            ->get();

        // Dashboard cards stay glanceable: three entries each, the rest
        // behind the existing "Verwalten" / "Alle Bestellungen" links.
        $standingCount = RestaurantStandingOrder::query()
            ->where('user_id', $user->id)
            ->active()
            ->count();
        $standing = RestaurantStandingOrder::query()
            ->where('user_id', $user->id)
            ->active()
            ->with('items.product.translations')
            ->latest()
            ->limit(3)
            ->get();

        $recent = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->orders()
            ->collapseFutureRecurring()
            ->withCount('items')
            ->latest()
            ->limit(3)
            ->get();

        $invoices = RestaurantOrder::query()
            ->where('user_id', $user->id)
            ->whereNotNull('invoice_number')
            ->orderByDesc('invoiced_at')
            ->limit(5)
            ->get();

        return view('restaurant.dashboard', [
            'standingCount' => $standingCount,
            'thisWeek' => $thisWeek,
            'nextWeek' => $nextWeek,
            'weekLockState' => $weekLockState,
            'profile' => $user->restaurantProfile,
            'nextDay' => $nextDay,
            'upcomingCount' => $upcomingCount,
            'openRequests' => $openRequests,
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
