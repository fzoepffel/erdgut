<?php

namespace App\Http\Controllers;

use App\Enums\SpargelOrderStatus;
use App\Http\Requests\SpargelOrderRequest;
use App\Models\SpargelOrder;
use App\Services\SpargelOrderService;
use App\Support\SumUpCheckoutLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'deliveryMinKg' => SpargelOrderService::DELIVERY_MIN_KG,
        ]);
    }

    public function store(SpargelOrderRequest $request): RedirectResponse
    {
        $order = $this->service->createOrder($request->validated());

        return redirect()->route('public.spargel.pay', ['spargelOrder' => $order->id]);
    }

    public function pay(Request $request, SpargelOrder $spargelOrder): RedirectResponse|View
    {
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
        ]);
    }

    public function return(Request $request, SpargelOrder $spargelOrder): View
    {
        // Authoritative server-side verification — we don't trust the redirect.
        if ($spargelOrder->status === SpargelOrderStatus::PendingPayment) {
            $this->service->confirmPaidFromProvider($spargelOrder);
            $spargelOrder->refresh();
        }

        return view('spargel.return', [
            'order' => $spargelOrder,
        ]);
    }
}
