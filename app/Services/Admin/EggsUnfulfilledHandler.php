<?php

namespace App\Services\Admin;

use App\Mail\EggsAddOnUnfulfilledMail;
use App\Models\Box;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * When an admin saves a box for a customer who ordered the eggs add-on but
 * no eggs product was attached, refund them the eggs surcharge and send a
 * notification email.
 *
 * Refund mechanism: book the partial refund against the customer's most
 * recent paid Payment for the corresponding subscription. Provider-side
 * refund (SumUp API) is best-effort and lives outside this class — the
 * book-keeping happens here so the admin save is never blocked on network.
 */
final class EggsUnfulfilledHandler
{
    /**
     * @param  list<Box>  $boxes
     */
    public function handle(array $boxes, ?int $actorUserId = null): void
    {
        $refundAmount = round((float) config('box.eggs_dairy_add_on_eur', 4.5), 2);
        if ($refundAmount <= 0) {
            return;
        }

        foreach ($boxes as $box) {
            try {
                $this->handleSingle($box, $refundAmount, $actorUserId);
            } catch (\Throwable $e) {
                // Never let one customer's refund/email failure block the others.
                Log::error('eggs_unfulfilled_handler.failed', [
                    'box_id' => $box->id,
                    'user_id' => $box->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function handleSingle(Box $box, float $refundAmount, ?int $actorUserId): void
    {
        $subscription = Subscription::query()
            ->where('user_id', $box->user_id)
            ->latest()
            ->first();

        if ($subscription === null || $subscription->user === null) {
            return;
        }

        // Find the most recent successful payment to refund against. If the
        // customer hasn't been charged yet for this delivery cycle, we still
        // log the obligation — admin will need to credit the next charge.
        $payment = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', Payment::STATUS_PAID)
            ->latest()
            ->first();

        $reason = sprintf(
            'eggs_add_on_unfulfilled_box_%d_week_%s',
            $box->id,
            $box->week_start_date?->toDateString() ?? 'unknown',
        );

        if ($payment !== null) {
            $payment->recordRefund(
                amount: $refundAmount,
                actorUserId: $actorUserId ?? 0,
                reason: $reason,
                reference: 'auto-eggs-unfulfilled',
            );
        }

        AuditLogger::log(
            entityType: 'box',
            entityId: (int) $box->id,
            action: 'eggs_addon_unfulfilled',
            fromState: null,
            toState: null,
            reason: $reason,
            metadata: [
                'refund_amount_eur' => $refundAmount,
                'payment_id' => $payment?->id,
                'subscription_id' => $subscription->id,
            ],
            actorUserId: $actorUserId ?? 0,
        );

        Mail::to($subscription->user)->queue(new EggsAddOnUnfulfilledMail(
            user: $subscription->user,
            box: $box,
            refundAmountEur: $refundAmount,
        ));
    }
}
