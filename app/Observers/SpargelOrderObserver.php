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
     * In an HTTP request, the actual `Mail::send()` calls are deferred
     * until after the response has been flushed to the browser. SMTP can
     * be slow on shared hosting (1-3 s per recipient observed on IONOS),
     * and the customer-facing order POST should never wait on it. In
     * CLI / PHPUnit context there is no terminate lifecycle, so we send
     * inline so artisan commands and tests still observe the side
     * effect.
     *
     * Failures are logged and swallowed — admin notifications must never
     * block the customer-facing flow.
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

        $send = function () use ($order, $adminEmails, $mailFactory): void {
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
        };

        if (app()->runningInConsole()) {
            $send();
        } else {
            app()->terminating($send);
        }
    }
}
