<?php

namespace App\Services\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\ShopOrder;
use App\Support\InvoiceSequence;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proper Rechnungen for shop orders: any non-mirror order can be invoiced
 * with a number from the shared yearly ledger ({@see InvoiceSequence}).
 * Mirrors of restaurant orders invoice through the B2B path instead, and
 * coupon-discounted orders are blocked for now (the gross discount has no
 * per-line net/VAT attribution — adjust prices in the order editor instead).
 */
class ShopOrderInvoiceService
{
    public function issueInvoice(ShopOrder $order): ShopOrder
    {
        $order = DB::transaction(function () use ($order): ShopOrder {
            /** @var ShopOrder $fresh */
            $fresh = ShopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->restaurant_order_id !== null) {
                throw new RuntimeException(__('shop.admin.invoice_blocked_mirror'));
            }
            if ($fresh->isInvoiced()) {
                throw new RuntimeException('Order already invoiced.');
            }
            if (in_array($fresh->status, [ShopOrderStatus::Cancelled, ShopOrderStatus::Failed, ShopOrderStatus::Refunded], true)) {
                throw new RuntimeException(__('shop.admin.invoice_blocked_status'));
            }
            if ((float) $fresh->total_amount_eur <= 0) {
                throw new RuntimeException('Cannot invoice a zero-total order.');
            }
            if ((float) $fresh->discount_eur > 0) {
                throw new RuntimeException(__('shop.admin.invoice_blocked_discount'));
            }

            $invoicedAt = Carbon::now();
            $number = InvoiceSequence::next($invoicedAt);

            // Payment terms count from delivery, never before the invoice
            // date; an editor-set due date wins (same rule as B2B).
            if ($fresh->invoice_due_date !== null) {
                $dueDate = Carbon::parse($fresh->invoice_due_date);
            } else {
                $dueDays = (int) config('shop.restaurant_invoice_due_days', 7);
                $deliveryDate = $fresh->expected_delivery_date !== null
                    ? Carbon::parse($fresh->expected_delivery_date)
                    : $invoicedAt;
                $dueDate = $deliveryDate->copy()->addDays($dueDays);
                if ($dueDate->lt($invoicedAt)) {
                    $dueDate = $invoicedAt->copy()->addDays($dueDays);
                }
            }

            $fresh->forceFill([
                'invoice_number' => $number,
                'invoiced_at' => $invoicedAt,
                'invoice_due_date' => $dueDate->toDateString(),
            ])->save();

            return $fresh;
        });

        return $order->refresh();
    }

    /**
     * Render the Rechnung PDF for a shop order. $previewNumber marks a
     * draft for not-yet-issued orders.
     */
    public function renderPdf(ShopOrder $order, ?string $previewNumber = null): DomPDF
    {
        $order->loadMissing('items.product.translations');

        return Pdf::loadView('shop.pdf.invoice', [
            'order' => $order,
            'previewNumber' => $previewNumber,
            'vatByRate' => ShopOrderService::vatByRate($order),
            'seller' => [
                'name' => (string) config('shop.company_name'),
                'street' => (string) config('shop.company_street'),
                'postal_city' => (string) config('shop.company_postal_city'),
                'phone' => (string) config('shop.company_phone'),
                'email' => (string) config('shop.company_email'),
                'vat_id' => (string) config('shop.company_vat_id'),
                'tax_number' => (string) config('shop.company_tax_number'),
                'iban' => (string) config('shop.company_iban'),
                'bank' => (string) config('shop.company_bank'),
                'register' => (string) config('shop.company_register'),
            ],
        ])->setPaper('a4');
    }
}
