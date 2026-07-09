<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Restaurant (B2B) account self-service: business/contact data, the billing
 * address (which also prefills every checkout), and the password — so the
 * data captured at registration (or by an admin-created account) is no
 * longer frozen. Email stays fixed: it is the account identity and the
 * invoice recipient; changing it goes through Erdgut.
 */
class RestaurantAccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('restaurant.account.edit', [
            'user' => $request->user(),
            'profile' => $request->user()->restaurantProfile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'billing_street' => ['required', 'string', 'max:160'],
            'billing_postal_code' => ['required', 'string', 'max:16'],
            'billing_city' => ['required', 'string', 'max:120'],
        ]);

        $request->user()->forceFill(['phone' => $validated['phone'] ?? null])->save();
        $request->user()->restaurantProfile->forceFill([
            'business_name' => $validated['business_name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'billing_street' => $validated['billing_street'],
            'billing_postal_code' => $validated['billing_postal_code'],
            'billing_city' => $validated['billing_city'],
        ])->save();

        return redirect()->route('app.restaurant.account.edit')
            ->with('status', __('restaurant.account_saved'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->forceFill(['password' => Hash::make($validated['password'])])->save();

        return redirect()->route('app.restaurant.account.edit')
            ->with('status', __('restaurant.password_saved'));
    }
}
