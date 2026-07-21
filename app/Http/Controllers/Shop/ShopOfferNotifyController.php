<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\ShopReducedOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public waitlist endpoints for the reduced-offer banner: sign up to be
 * notified when the full offer returns, and one-click unsubscribe.
 */
class ShopOfferNotifyController extends Controller
{
    public function __construct(private readonly ShopReducedOfferService $reducedOffer) {}

    /**
     * Add an email to the waitlist. Always redirects back with a neutral
     * success flash (never reveals whether the address was already on the list).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $this->reducedOffer->subscribe($validated['email']);

        return back()->with('offer_notify_status', __('shop.reduced_offer.signup_success'));
    }

    /**
     * One-click unsubscribe from the emailed link. Idempotent: an unknown or
     * already-removed token still lands on the confirmation page.
     */
    public function unsubscribe(string $token): View
    {
        $this->reducedOffer->unsubscribeByToken($token);

        return view('shop.notify-unsubscribed');
    }
}
