<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->orderByRaw('status = ? desc', [SubscriptionStatus::Active->value])
            ->latest()
            ->first();

        // TODO(ERD-090): Replace billing-date placeholder with computed recurring billing date.
        $billingDatePlaceholder = 'Billing date available after payment setup.';

        return view('subscriptions.show', [
            'subscription' => $subscription,
            'billingDatePlaceholder' => $billingDatePlaceholder,
        ]);
    }
}
