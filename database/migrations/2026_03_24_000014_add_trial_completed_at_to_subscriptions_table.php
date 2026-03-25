<?php

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_completed_at')->nullable()->after('next_charge_date');
        });

        // Subscriptions that already have a paid charge are past the one-time trial window.
        Subscription::query()
            ->whereHas('payments', fn ($q) => $q->where('status', Payment::STATUS_PAID))
            ->each(function (Subscription $subscription): void {
                $firstPaidAt = $subscription->payments()
                    ->where('status', Payment::STATUS_PAID)
                    ->orderBy('paid_at')
                    ->value('paid_at');
                $subscription->forceFill([
                    'trial_completed_at' => $firstPaidAt ?? now(),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('trial_completed_at');
        });
    }
};
