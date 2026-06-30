<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The verified-restaurant area. A placeholder dashboard for now — the B2B
 * shop, requests and invoices arrive in later tickets (R3+).
 */
class RestaurantDashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('restaurant.dashboard', [
            'profile' => $request->user()->restaurantProfile,
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
