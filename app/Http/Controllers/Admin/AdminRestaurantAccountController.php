<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Admin queue of restaurant applications, so an admin can review pending
 * accounts without hunting for the email. Each row links to the same signed
 * review page the notification email uses.
 */
class AdminRestaurantAccountController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $adminId = (int) $request->user()->id;

        $profiles = RestaurantProfile::query()
            ->with('user')
            // Pending first, then newest.
            ->orderByRaw("CASE WHEN verification_status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(30)
            ->through(function (RestaurantProfile $profile) use ($adminId): RestaurantProfile {
                $profile->setAttribute('review_url', URL::signedRoute('restaurant.verify.review', [
                    'restaurantProfile' => $profile->id,
                    'admin' => $adminId,
                ]));

                return $profile;
            });

        return view('admin.restaurant-accounts.index', ['profiles' => $profiles]);
    }
}
