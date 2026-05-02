<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill: any subscription that's currently `active` but never stored
     * a SumUp token represents an abandoned wizard run (created by
     * SubscriptionOnboardingService::ensureSubscription before payment was
     * captured). Going forward those rows are created in `pending_payment`
     * state, but the existing rows still claim `active`, which makes the
     * dashboard pretend the customer has a real subscription.
     *
     * Flip them. We deliberately do NOT touch rows that have a token
     * (those are real Active subs) or a manual billing mode (those are
     * activated by the admin without SumUp).
     */
    public function up(): void
    {
        DB::table('subscriptions')
            ->where('status', 'active')
            ->whereNull('sumup_token')
            ->where(function ($query) {
                // Subscriptions explicitly billed by hand (manual_invoice / manual_cash)
                // legitimately have no SumUp token even when Active. Leave them alone.
                $query->where('billing_mode', 'sumup_recurring')
                    ->orWhereNull('billing_mode');
            })
            ->update(['status' => 'pending_payment']);
    }

    public function down(): void
    {
        DB::table('subscriptions')
            ->where('status', 'pending_payment')
            ->update(['status' => 'active']);
    }
};
