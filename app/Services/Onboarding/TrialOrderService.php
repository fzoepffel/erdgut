<?php

namespace App\Services\Onboarding;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\PaymentChargeInitiator;
use App\Mail\TrialOrderConfirmationMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Domain service for creating and progressing trial orders.
 *
 * Responsibilities:
 * - Create the Order row + associated pending Payment row.
 * - Kick off a SumUp one-time checkout (no stored mandate).
 * - On webhook or return-url completion, mark order paid + send confirmation.
 */
class TrialOrderService
{
    public function __construct(private readonly SumUpService $sumUpService) {}

    /**
     * @param  array<string, mixed>  $payload
     *                                         Expected keys:
     *                                         - address_id (int)
     *                                         - box_size (string)
     *                                         - preferences (array)
     *                                         - allergies (array)
     *                                         - preferred_timeslot (?string)
     *                                         - preferred_delivery_window (?string)
     *                                         - special_instructions (?string)
     *                                         - is_test (bool)
     */
    public function createOrder(User $user, array $payload): Order
    {
        $amount = round((float) config('box.trial_price_oneoff_ttc', 35), 2);

        return DB::transaction(function () use ($user, $payload, $amount): Order {
            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $payload['address_id'] ?? null,
                'product_type' => OrderProductType::TrialBox,
                'box_size' => $payload['box_size'] ?? 'medium',
                'preferences' => $payload['preferences'] ?? [],
                'allergies' => $payload['allergies'] ?? [],
                'preferred_timeslot' => $payload['preferred_timeslot'] ?? null,
                'preferred_delivery_window' => $payload['preferred_delivery_window'] ?? null,
                'special_instructions' => $payload['special_instructions'] ?? null,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => OrderStatus::PendingPayment,
                'is_test' => (bool) ($payload['is_test'] ?? false),
            ]);

            return $order;
        });
    }

    /**
     * Create (or re-use the latest pending) Payment row and initiate SumUp checkout.
     *
     * Returns the checkout object from SumUp; the controller decides whether to
     * redirect to a hosted checkout URL or render a widget.
     */
    public function initiateCheckout(Order $order, ?string $returnUrl = null): object
    {
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->first();

        $attemptNumber = ($payment?->attempt_number ?? 0) + 1;

        if ($payment === null) {
            $payment = new Payment([
                'order_id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'status' => Payment::STATUS_PENDING,
                'attempt_number' => 1,
                'charge_initiator' => PaymentChargeInitiator::TrialCustomer->value,
                'initiated_by_user_id' => $order->user_id,
            ]);
            $payment->save();
            $attemptNumber = 1;
        }

        $reference = 'order-'.$order->id.'-attempt-'.$attemptNumber.'-'.now()->timestamp;

        $extra = [];
        if ($returnUrl !== null) {
            $extra['return_url'] = $returnUrl;
            $extra['redirect_url'] = $returnUrl;
        }

        $checkout = $this->sumUpService->createOneTimeCheckout(
            amount: (float) $order->amount,
            checkoutReference: $reference,
            description: (string) config('box.trial_description', 'Erdgut trial box'),
            customerId: null,
            extraParams: $extra,
        );

        $checkoutId = (string) (data_get($checkout, 'id') ?? '');

        if ($checkoutId !== '') {
            $payment->forceFill(['sumup_checkout_id' => $checkoutId])->save();
        }

        SumUpCheckoutLogger::info('Trial order: SumUp one-time checkout created.', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'checkout_id' => $checkoutId !== '' ? $checkoutId : null,
            'checkout_reference' => $reference,
        ]);

        return $checkout;
    }

    /**
     * Idempotently mark an order (and its pending payment) as paid. Safe to call
     * from either the return-url callback or the webhook handler.
     */
    public function markOrderPaid(Order $order): void
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->markPaid();

            Payment::query()
                ->where('order_id', $order->id)
                ->where('status', Payment::STATUS_PENDING)
                ->get()
                ->each
                ->markPaid();
        });

        try {
            Mail::to($order->user->email)->queue(new TrialOrderConfirmationMail($order));
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('Failed to queue trial confirmation mail.', [
                'order_id' => $order->id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }
    }
}
