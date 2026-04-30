<?php

namespace App\Http\Controllers;

use App\Enums\SpargelOrderStatus;
use App\Http\Requests\SpargelOrderRequest;
use App\Mail\SpargelOrderConfirmationMail;
use App\Models\SpargelOrder;
use App\Services\SpargelOrderService;
use App\Support\SumUpCheckoutLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Standalone trial-shop controller for the spargel sales test.
 *
 * Public flow — no auth, no User row, no Order row, no Product row.
 * Each visit is one anonymous purchase against a {@see SpargelOrder}.
 *
 *   GET  /spargel              show form
 *   POST /spargel              validate + create order + redirect to /spargel/{order}/pay
 *   GET  /spargel/{order}/pay  initiate SumUp checkout + render widget
 *   GET  /spargel/{order}/return  SumUp redirect target — verify + show thank-you
 */
class SpargelOrderController extends Controller
{
    public function __construct(private readonly SpargelOrderService $service) {}

    public function show(): View
    {
        return view('spargel.show', [
            'pricePerKgEur' => SpargelOrderService::PRICE_PER_KG_EUR,
            'deliveryFeeEur' => SpargelOrderService::DELIVERY_FEE_EUR,
            'deliveryMinGrams' => SpargelOrderService::DELIVERY_MIN_GRAMS,
            'quantityStepGrams' => SpargelOrderService::QUANTITY_STEP_GRAMS,
            'quantityOptions' => SpargelOrderService::quantityOptions(),
            'vatRatePercent' => SpargelOrderService::VAT_RATE_PERCENT,
            'nextDeliveryMonday' => $this->service->nextDeliveryMonday(),
        ]);
    }

    public function store(SpargelOrderRequest $request): RedirectResponse
    {
        $order = $this->service->createOrder($request->validated());

        // Pay-in-person path skips the SumUp checkout — the order goes
        // straight to the confirmation page (status stays PendingPayment
        // until an admin marks it paid via /spargel-admin). Send the
        // customer their confirmation email now since this is the only
        // moment they'll see "we received your order" before they show
        // up to collect.
        if ($order->payment_method === 'in_person') {
            $this->queueConfirmationMail($order);

            return redirect()->route('public.spargel.return', ['spargelOrder' => $order->id]);
        }

        return redirect()->route('public.spargel.pay', ['spargelOrder' => $order->id]);
    }

    public function pay(Request $request, SpargelOrder $spargelOrder): RedirectResponse|View
    {
        // In-person orders never use the SumUp pay-step — bounce them to
        // the confirmation page directly.
        if ($spargelOrder->payment_method === 'in_person') {
            return redirect()->route('public.spargel.return', ['spargelOrder' => $spargelOrder->id]);
        }

        if ($spargelOrder->status !== SpargelOrderStatus::PendingPayment) {
            return redirect()->route('public.spargel.return', ['spargelOrder' => $spargelOrder->id]);
        }

        $returnUrl = route('public.spargel.return', ['spargelOrder' => $spargelOrder->id], absolute: true);

        $checkoutId = null;
        $checkoutError = null;
        try {
            $checkout = $this->service->initiateCheckout($spargelOrder, $returnUrl);
            $checkoutId = (string) (data_get($checkout, 'id') ?? '') ?: null;

            if ($checkoutId === null) {
                $checkoutError = 'no_checkout_id';
                SumUpCheckoutLogger::warning('Spargel pay: SumUp returned no checkout id.', [
                    'spargel_order_id' => $spargelOrder->id,
                ]);
            }
        } catch (Throwable $e) {
            report($e);
            $checkoutError = 'checkout_creation_failed';
            SumUpCheckoutLogger::warning('Spargel pay: SumUp checkout creation failed.', [
                'spargel_order_id' => $spargelOrder->id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }

        return view('spargel.pay', [
            'order' => $spargelOrder,
            'checkoutId' => $checkoutId,
            'checkoutError' => $checkoutError,
            'returnUrl' => $returnUrl,
            'vatRatePercent' => SpargelOrderService::VAT_RATE_PERCENT,
        ]);
    }

    public function return(Request $request, SpargelOrder $spargelOrder): View
    {
        // Authoritative server-side verification — we don't trust the redirect.
        if ($spargelOrder->status === SpargelOrderStatus::PendingPayment && $spargelOrder->payment_method !== 'in_person') {
            $wasPaid = $this->service->confirmPaidFromProvider($spargelOrder);
            $spargelOrder->refresh();

            if ($wasPaid) {
                $this->queueConfirmationMail($spargelOrder);
            }
        }

        return view('spargel.return', [
            'order' => $spargelOrder,
        ]);
    }

    /**
     * Send the trial-shop confirmation email. Idempotent against mail-
     * driver failures — we never want a transient mail outage to break
     * the order-creation flow itself.
     */
    private function queueConfirmationMail(SpargelOrder $order): void
    {
        try {
            Mail::to($order->email)->queue(new SpargelOrderConfirmationMail($order));
        } catch (Throwable $e) {
            report($e);
            SumUpCheckoutLogger::warning('Spargel order: failed to queue confirmation email.', [
                'spargel_order_id' => $order->id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);
        }
    }
}
