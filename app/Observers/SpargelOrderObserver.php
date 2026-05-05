<?php

namespace App\Observers;

use App\Enums\SpargelOrderStatus;
use App\Mail\SpargelOrderPaidAdminNotification;
use App\Models\SpargelOrder;
use App\Models\User;
use App\Services\SpargelOrderService;
use App\Support\SumUpWebhookHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Side effects on SpargelOrder lifecycle.
 *
 * Currently: when status transitions to PAID we notify every admin user
 * with the full order detail. Triggered for both confirmation paths
 * (return-url verification in {@see SpargelOrderService}
 * and the SumUp webhook in {@see SumUpWebhookHandler}) since
 * both end with `forceFill(['status' => Paid, ...])->save()`.
 */
class SpargelOrderObserver
{
    public function updated(SpargelOrder $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = $order->getOriginal('status');
        $previousValue = $previous instanceof SpargelOrderStatus ? $previous->value : (string) $previous;
        $currentValue = $order->status instanceof SpargelOrderStatus ? $order->status->value : (string) $order->status;

        if ($currentValue !== SpargelOrderStatus::Paid->value || $previousValue === SpargelOrderStatus::Paid->value) {
            return;
        }

        $this->dispatchAdminNotification($order);
    }

    private function dispatchAdminNotification(SpargelOrder $order): void
    {
        $adminEmails = User::query()
            ->where('is_admin', true)
            ->pluck('email')
            ->all();

        if ($adminEmails === []) {
            return;
        }

        // One mail per admin so each envelope has a single To: header.
        // Mail failures should never block the payment flow; we log and
        // move on so the customer's confirmation isn't held up by an SMTP
        // hiccup at our end.
        foreach ($adminEmails as $email) {
            try {
                Mail::to($email)->send(new SpargelOrderPaidAdminNotification($order));
            } catch (Throwable $e) {
                Log::warning('spargel.admin_notification.send_failed', [
                    'spargel_order_id' => $order->id,
                    'recipient' => $email,
                    'exception_class' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
