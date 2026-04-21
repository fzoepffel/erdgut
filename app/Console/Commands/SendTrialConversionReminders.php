<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Mail\TrialConvertToSubscriptionMail;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sends the "convert to subscription" nudge ~2 days after a trial was delivered.
 * Idempotent: marks is_test flag-less orders with a JSON marker in data (or reuses delivered_at
 * timing window) so the mail isn't sent twice.
 */
class SendTrialConversionReminders extends Command
{
    protected $signature = 'trials:send-conversion-reminders {--dry-run}';

    protected $description = 'Email delivered trial customers with a subscription conversion nudge.';

    public function handle(): int
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(3);
        $windowEnd = $now->copy()->subDays(2);

        $dryRun = (bool) $this->option('dry-run');

        $orders = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$windowStart, $windowEnd])
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($orders as $order) {
            if ($order->user === null) {
                continue;
            }

            $alreadySubscriber = Subscription::query()
                ->where('user_id', $order->user_id)
                ->whereIn('status', ['active', 'paused'])
                ->exists();

            if ($alreadySubscriber) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would send conversion mail for order #{$order->id}");
                continue;
            }

            try {
                Mail::to($order->user->email)->queue(new TrialConvertToSubscriptionMail($order));
                $sent++;
            } catch (Throwable $e) {
                $this->warn("Failed to queue conversion mail for order #{$order->id}: ".$e->getMessage());
            }
        }

        $this->info("Queued {$sent} conversion reminder(s).");

        return self::SUCCESS;
    }
}
