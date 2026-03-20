<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentSetupController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $subscription = $this->resolveSubscription($request);

        if ($subscription === null) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'subscription-missing');
        }

        if (! empty($subscription->sumup_token)) {
            return redirect()
                ->route('app.subscriptions.show')
                ->with('status', 'payment-already-setup');
        }

        return view('payments.setup', [
            'subscription' => $subscription,
        ]);
    }

    private function resolveSubscription(Request $request): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $request->user()->id)
            ->orderByRaw('status = ? desc', [SubscriptionStatus::Active->value])
            ->latest()
            ->first();
    }
}
