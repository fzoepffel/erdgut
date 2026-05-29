<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
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

        $payments = $user
            ->payments()
            ->with('subscription')
            ->orderByDesc('created_at')
            ->get();

        $currentYear = now()->year;
        $ytdPaid = $payments->where('status', Payment::STATUS_PAID)->filter(fn ($p) => $p->created_at->year === $currentYear)->sum('amount');
        $ytdRefunded = $payments->where('status', Payment::STATUS_REFUNDED)->filter(fn ($p) => $p->created_at->year === $currentYear)->sum('refund_amount');
        $ytdCount = $payments->where('status', Payment::STATUS_PAID)->filter(fn ($p) => $p->created_at->year === $currentYear)->count();
        $ytdAvg = $ytdCount > 0 ? $ytdPaid / $ytdCount : null;

        return view('payments.history', [
            'payments' => $payments,
            'subscription' => $subscription,
            'hasPaymentMethod' => $hasPaymentMethod,
            'canSetupPayment' => $canSetupPayment,
            'canUpdatePayment' => $canUpdatePayment,
            'user' => $user,
            'ytdPaid' => $ytdPaid,
            'ytdRefunded' => $ytdRefunded,
            'ytdCount' => $ytdCount,
            'ytdAvg' => $ytdAvg,
        ]);
    }
}
