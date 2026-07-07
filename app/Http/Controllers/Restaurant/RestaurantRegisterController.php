<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\AccountType;
use App\Enums\RestaurantVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\RestaurantRegisterRequest;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Support\RestaurantApplicationAdminNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Public restaurant (B2B) account application. Creates a *pending* account
 * (no login — the applicant can't enter until an admin verifies them) and
 * notifies admins to review it.
 */
class RestaurantRegisterController extends Controller
{
    public function create(): View
    {
        return view('restaurant.register');
    }

    public function store(RestaurantRegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $profile = DB::transaction(function () use ($validated): RestaurantProfile {
            $user = new User;
            $user->fill([
                'name' => $validated['business_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                // Persist the signup locale so review/approval emails render
                // in the language the restaurant applied in.
                'locale' => app()->getLocale(),
                'password' => Hash::make($validated['password']),
            ]);
            $user->account_type = AccountType::Restaurant;
            // Restaurants never run the self-service email-verification loop —
            // the admin review IS the vetting (and reaches them by this email).
            $user->email_verified_at = now();
            $user->save();

            return RestaurantProfile::query()->create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'contact_name' => $validated['contact_name'],
                'handelsregisternummer' => $validated['handelsregisternummer'] ?? null,
                'ust_id_nr' => $validated['ust_id_nr'] ?? null,
                'billing_street' => $validated['billing_street'],
                'billing_postal_code' => $validated['billing_postal_code'],
                'billing_city' => $validated['billing_city'],
                'verification_status' => RestaurantVerificationStatus::Pending,
            ]);
        });

        RestaurantApplicationAdminNotifier::applied($profile);

        return redirect()->route('restaurant.register.submitted');
    }

    public function submitted(): View
    {
        return view('restaurant.register-submitted');
    }
}
