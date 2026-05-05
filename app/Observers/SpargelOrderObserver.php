<?php

namespace App\Observers;

use App\Enums\SpargelOrderStatus;
use App\Mail\SpargelOrderOpenedAdminNotification;
use App\Mail\SpargelOrderPaidAdminNotification;
use App\Models\SpargelOrder;
use App\Models\User;
use App\Services\SpargelOrderService;
use App\Support\SumUpWebhookHandler;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Side effects on SpargelOrder lifecycle.
 *
 * Two admin notifications:
 *  - On `created`: every new order produces an "opened" email so admins
 *    see in-person orders immediately and can spot SumUp orders that
 *    later stall (the matching `paid` email never arrives).
 *  - On `updated` with status flipping to PAID: a follow-up "paid" email.
 *    Triggered for both confirmation paths (return-url verification in
 *    {@see SpargelOrderService} and the SumUp webhook in
 *    {@see SumUpWebhookHandler}) since both end with
 *    `forceFill(['status' => Paid, ...])->save()`.
 */
class SpargelOrderObserver
{
    public function created(SpargelOrder $order): void
    {
        $this->dispatchToAdmins($order, fn (): Mailable => new SpargelOrderOpenedAdminNotification($order));
    }

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

        $this->dispatchToAdmins($order, fn (): Mailable => new SpargelOrderPaidAdminNotification($order));
    }

    /**
     * Send the given mailable to every is_admin user.
     *
     * One envelope per recipient so each To: is clean and bounces are
     * attributable. Failures are logged and swallowed — admin
     * notifications must never block the customer-facing flow (an SMTP
     * hiccup at our end shouldn't prevent the order from being created
     * or marked paid).
     *
     * @param  callable(): Mailable  $mailFactory
     */
    private function dispatchToAdmins(SpargelOrder $order, callable $mailFactory): void
    {
        $adminEmails = User::query()
            ->where('is_admin', true)
            ->pluck('email')
            ->all();

        if ($adminEmails === []) {
            return;
        }

        foreach ($adminEmails as $email) {
            try {
                Mail::to($email)->send($mailFactory());
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
