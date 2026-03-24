<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $subscription = $user !== null
            ? Subscription::billingSubscriptionForUser($user)
            : null;

        $hasPaymentMethod = $subscription !== null && ! empty($subscription->sumup_token);
        $canSetupPayment = $subscription !== null
            && $subscription->status !== SubscriptionStatus::Cancelled
            && ! $hasPaymentMethod;
        $canUpdatePayment = $subscription !== null
            && $subscription->status !== SubscriptionStatus::Cancelled
            && $hasPaymentMethod;

        $billingDateDisplay = $subscription?->next_charge_date !== null
            ? $subscription->next_charge_date->format('d.m.Y')
            : __('Set after first charge');

        $payments = $user
            ->payments()
            ->with('subscription')
            ->orderByDesc('created_at')
            ->get();

        return view('payments.history', [
            'payments' => $payments,
            'subscription' => $subscription,
            'hasPaymentMethod' => $hasPaymentMethod,
            'canSetupPayment' => $canSetupPayment,
            'canUpdatePayment' => $canUpdatePayment,
            'billingDateDisplay' => $billingDateDisplay,
        ]);
    }
}
