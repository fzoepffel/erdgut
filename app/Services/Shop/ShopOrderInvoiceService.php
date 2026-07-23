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
            // A draft may already carry a manually-edited number; reuse it
            // instead of consuming the next sequence value.
            $number = $fresh->invoice_number;
            if ($number === null || trim($number) === '') {
                $number = InvoiceSequence::next($invoicedAt);
            } else {
                $this->bumpSequencePastManualNumber($number, $invoicedAt);
            }

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
     * Mark an issued invoice as not-yet-issued again: clears invoiced_at
     * and the due date, but keeps invoice_number so the admin can reuse or
     * edit it before re-issuing.
     */
    public function revertInvoice(ShopOrder $order): ShopOrder
    {
        $order = DB::transaction(function () use ($order): ShopOrder {
            /** @var ShopOrder $fresh */
            $fresh = ShopOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->restaurant_order_id !== null) {
                throw new RuntimeException(__('shop.admin.invoice_blocked_mirror'));
            }
            if (! $fresh->isInvoiced()) {
                throw new RuntimeException(__('shop.admin.invoice_not_issued'));
            }

            $fresh->forceFill([
                'invoiced_at' => null,
                'invoice_due_date' => null,
            ])->save();

            return $fresh;
        });

        return $order->refresh();
    }

    /**
     * When a draft carries a manual EB-style number at or past the current
     * ledger head, advance the counter so the next auto-assigned number
     * cannot collide with it.
     */
    private function bumpSequencePastManualNumber(string $number, Carbon $at): void
    {
        $prefix = (string) config('shop.restaurant_invoice_prefix', 'EB');
        if (! preg_match('/^'.preg_quote($prefix, '/').'(\d{2})(\d{4})$/', $number, $m)) {
            return;
        }
        // Only bump when the year matches the issue year.
        if ($m[1] !== $at->format('y')) {
            return;
        }

        $sequence = (int) $m[2];
        $preview = InvoiceSequence::preview($at);
        if (! preg_match('/^'.preg_quote($prefix, '/').'\d{2}(\d{4})$/', $preview, $p)) {
            return;
        }
        $nextSequence = (int) $p[1];
        if ($sequence >= $nextSequence) {
            InvoiceSequence::setNext($sequence + 1);
        }
    }

    /**
     * Money edits from the WYSIWYG invoice editor, net-forward like the B2B
     * editor: per-line net entry converts to the gross snapshots the shop
     * order stores (gross = net × (100 + rate)/100, VAT = gross − net).
     * Handles renames, removals, NEW off-catalog lines and the delivery fee
     * (net entry). Recomputes the order totals and, exactly like
     * ShopOrderService::updateOrder, re-opens the paid/outstanding
     * settlement when the owed total moves away from what was paid.
     * Quantity changes flow into stock via the item rows
     * (InventoryCacheObserver), the same path the order editor uses.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updatePricing(
        ShopOrder $order,
        array $lines,
        ?float $deliveryFeeNetEur = null,
        ?int $deliveryFeeVatRatePercent = null,
    ): ShopOrder {
        if ($order->restaurant_order_id !== null) {
            throw new RuntimeException(__('shop.admin.invoice_blocked_mirror'));
        }

        return DB::transaction(function () use ($order, $lines, $deliveryFeeNetEur, $deliveryFeeVatRatePercent): ShopOrder {
            // Lazy legacy snapshot BEFORE any recompute: orders paid before
            // paid_amount_eur existed collected their pre-edit total.
            if ($order->paid_at !== null && $order->paid_amount_eur === null) {
                $order->forceFill(['paid_amount_eur' => $order->total_amount_eur])->save();
            }

            $items = $order->items()->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $payload) {
                $item = $items->get((int) ($payload['id'] ?? 0));

                // New line (no id): an off-catalog custom item, priced net.
                if ($item === null) {
                    $name = trim((string) ($payload['name'] ?? ''));
                    if ($name === '' || ! empty($payload['remove'])) {
                        continue;
                    }

                    $qty = round((float) ($payload['quantity'] ?? 1), 4);
                    $rate = (int) ($payload['vat_rate_percent'] ?? (int) config('shop.vat_rate_percent', 7));
                    $net = round((float) ($payload['net_total_eur'] ?? 0), 2);
                    $gross = round($net * (100 + $rate) / 100, 2);

                    $order->items()->create([
                        'product_id' => null,
                        'variant_id' => null,
                        'name_snapshot' => $name,
                        'quantity' => $qty > 0 ? $qty : 1,
                        'unit' => trim((string) ($payload['unit'] ?? '')) !== '' ? (string) $payload['unit'] : 'piece',
                        'unit_price_snapshot_eur' => $qty > 0 ? round($gross / $qty, 4) : $gross,
                        'vat_rate_snapshot_percent' => $rate,
                        'line_total_eur' => $gross,
                        'vat_amount_eur' => round($gross - $net, 2),
                    ]);

                    continue;
                }

                if (! empty($payload['remove'])) {
                    $item->delete();

                    continue;
                }

                $qty = round((float) ($payload['quantity'] ?? $item->quantity), 4);
                $rate = (int) ($payload['vat_rate_percent'] ?? $item->vat_rate_snapshot_percent);
                $net = round((float) ($payload['net_total_eur'] ?? ((float) $item->line_total_eur - (float) $item->vat_amount_eur)), 2);

                $gross = round($net * (100 + $rate) / 100, 2);

                // Keep the precise per-unit snapshot when the line is untouched
                // (same net, quantity and rate): recomputing it from the
                // 2-decimal total would degrade e.g. 0,0215 €/g to 0,0213.
                // Only lines whose total actually changed take a fresh snapshot.
                $storedNet = round((float) $item->line_total_eur - (float) $item->vat_amount_eur, 2);
                $lineUnchanged = abs($net - $storedNet) < 0.005
                    && abs($qty - (float) $item->quantity) < 0.00005
                    && $rate === (int) $item->vat_rate_snapshot_percent;
                $snapshot = $lineUnchanged
                    ? (float) $item->unit_price_snapshot_eur
                    : ($qty > 0 ? round($gross / $qty, 4) : 0);

                $item->forceFill([
                    'quantity' => $qty,
                    'vat_rate_snapshot_percent' => $rate,
                    'unit_price_snapshot_eur' => $snapshot,
                    'line_total_eur' => $gross,
                    'vat_amount_eur' => round($gross - $net, 2),
                    // Per-document print name; blank resets to the real name.
                    'display_name_override' => array_key_exists('display_name', $payload)
                        ? (trim((string) $payload['display_name']) !== '' ? trim((string) $payload['display_name']) : null)
                        : $item->display_name_override,
                    // Display-only unit/qty for the invoice & receipt PDFs;
                    // blank clears the override and the natural unit is used.
                    'display_unit_override' => array_key_exists('display_unit', $payload)
                        ? (trim((string) $payload['display_unit']) !== '' ? trim((string) $payload['display_unit']) : null)
                        : $item->display_unit_override,
                    'display_quantity_override' => array_key_exists('display_quantity', $payload)
                        ? ($payload['display_quantity'] !== null && $payload['display_quantity'] !== ''
                            ? round((float) $payload['display_quantity'], 4)
                            : null)
                        : $item->display_quantity_override,
                ])->save();
            }

            // Delivery fee, net entry. Pickup orders never carry a fee.
            if ($order->fulfillment_mode === 'delivery' && $deliveryFeeNetEur !== null) {
                $feeRate = (int) ($deliveryFeeVatRatePercent ?? $order->delivery_fee_vat_rate_percent ?? 7);
                $feeGross = $deliveryFeeNetEur > 0 ? round($deliveryFeeNetEur * (100 + $feeRate) / 100, 2) : 0.0;
                $order->forceFill([
                    'delivery_fee_eur' => $feeGross,
                    'delivery_fee_vat_rate_percent' => $feeRate,
                    'delivery_fee_vat_amount_eur' => $feeGross > 0 ? round($feeGross - $deliveryFeeNetEur, 2) : 0.0,
                ])->save();
            }

            $itemsTotal = round((float) $order->items()->sum('line_total_eur'), 2);
            $total = round($itemsTotal - (float) $order->discount_eur + (float) $order->delivery_fee_eur, 2);

            // A fresh difference between paid and owed re-opens the
            // settlement; the previous one stays in the audit log.
            $reopens = $order->paid_at !== null
                && abs($total - (float) $order->paid_amount_eur) >= 0.01;

            $order->forceFill([
                'items_total_eur' => $itemsTotal,
                'total_amount_eur' => $total,
            ] + ($reopens ? [
                'outstanding_settled_amount_eur' => null,
                'outstanding_settled_at' => null,
                'outstanding_settled_by_user_id' => null,
            ] : []))->save();

            return $order->refresh();
        });
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
