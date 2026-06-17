<?php

namespace App\Http\Controllers;

use App\Enums\ShopOrderStatus;
use App\Models\BoxWaitlistSignup;
use App\Models\ShopOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Captures interest in the upcoming subscription boxes.
 *
 * Two entry points, both storing one row per unique email so a person is
 * only asked once:
 *   - {@see subscribe()}      homepage capture form (always interested)
 *   - {@see recordInterest()} post-payment checkout prompt (yes/no)
 */
class BoxWaitlistController extends Controller
{
    /**
     * Homepage waitlist capture. Records the email as interested and bounces
     * back to the homepage with a thank-you flash.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            // Honeypot — must stay empty for human submissions.
            'website' => ['nullable', 'prohibited'],
        ]);

        BoxWaitlistSignup::query()->updateOrCreate(
            ['email' => mb_strtolower(trim($validated['email']))],
            [
                'interested' => true,
                'source' => 'homepage',
                'locale' => app()->getLocale(),
                'ip_address' => $request->ip(),
            ],
        );

        return redirect()
            ->route('public.home')
            ->with('status', 'box-waitlist-subscribed');
    }

    /**
     * Post-payment checkout prompt. Stores the customer's yes/no answer
     * against their order email, then returns to the confirmation page (the
     * order is already complete at this point — this never blocks it).
     */
    public function recordInterest(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $validated = $request->validate([
            'interested' => ['required', 'boolean'],
        ]);

        // Only sensible for a paid order that carries an email we can key on.
        if ($shopOrder->status === ShopOrderStatus::Paid && filled($shopOrder->email)) {
            BoxWaitlistSignup::query()->updateOrCreate(
                ['email' => mb_strtolower(trim($shopOrder->email))],
                [
                    'interested' => (bool) $validated['interested'],
                    'source' => 'checkout',
                    'locale' => app()->getLocale(),
                    'ip_address' => $request->ip(),
                ],
            );
        }

        return redirect()->route('public.shop.return', ['shopOrder' => $shopOrder->id]);
    }
}
