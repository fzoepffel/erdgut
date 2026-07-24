<?php

namespace App\Services\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Restaurant\RestaurantCatalogService;
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

                $item->forceFill([
                    'quantity' => $qty,
                    'vat_rate_snapshot_percent' => $rate,
                    'unit_price_snapshot_eur' => $qty > 0 ? round($gross / $qty, 4) : 0,
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
     * Per-display-unit price for an invoice/receipt line, sourced from the
     * product's catalog price rather than the 2-decimal line total. Dividing
     * the total by the quantity mis-prices small amounts of per-100g/per-kg
     * goods (30 g of a 2,15 €-net/100 g product stores 0,64 € net, and
     * 0,64 ÷ 0,03 kg reads 21,33 €/kg instead of 21,50). Using the catalog
     * price with the B2B rounding order — round the gross price to the
     * display unit first, then extract VAT — gives a clean 21,50 €/kg for any
     * quantity ({@see RestaurantCatalogService::row}).
     *
     * A 2-cent guard keeps the column honest: when the catalog price no longer
     * reconciles with what the line was actually charged (a manual discount,
     * or a catalog price changed after the invoice issued), it falls back to
     * the line's effective rate (total ÷ display quantity), so an issued
     * invoice never shows a misleading current price.
     *
     * @return array{net: float, gross: float, displayUnit: string, factor: float}
     */
    public static function displayUnitPricing(ShopOrderItem $item): array
    {
        $qty = (float) $item->quantity;
        $product = $item->product;
        $actualNet = round((float) $item->line_total_eur - (float) $item->vat_amount_eur, 2);
        $actualGross = (float) $item->line_total_eur;

        // An admin display override (e.g. "0,03 kg" shown for a 30 g order)
        // fixes the printed unit and quantity. When that override is only a
        // unit normalisation of the real amount, the price must still come
        // from the catalog: dividing the total by the small display quantity
        // reintroduces the exact rounding error the product branch avoids
        // (0,65 ÷ 0,03 kg = 21,67 instead of 21,50). The 2-cent guard tells a
        // normalisation apart from a genuine re-quantification ("2 Stück" for
        // 300 g), which keeps the total-derived rate so the row multiplies out.
        $overrideUnit = trim((string) ($item->display_unit_override ?? ''));
        $overrideQtyRaw = $item->display_quantity_override;
        if ($overrideUnit !== '' && $overrideQtyRaw !== null && (float) $overrideQtyRaw > 0) {
            $overrideLower = mb_strtolower($overrideUnit);
            $overrideScale = in_array($overrideLower, ['g', 'ml'], true) ? 1000 : 1;
            $displayQty = (float) $overrideQtyRaw / $overrideScale;
            $displayUnit = match ($overrideLower) {
                'g' => 'kg',
                'ml' => 'L',
                'l' => 'L',
                default => $overrideUnit,
            };

            if ($displayQty > 0 && $product?->unit !== null && (float) $product->unit_price > 0) {
                $natUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
                if ($natUnit === '100g') {
                    $natUnit = 'kg';
                }
                $natFactor = max(1, $product->unit->canonicalUnitsPerDisplay($natUnit));
                $rate = (int) $item->vat_rate_snapshot_percent;
                $grossPerDisplay = round((float) $product->unit_price * $natFactor, 2);
                $netPerDisplay = round($grossPerDisplay - ShopOrderService::vatAmountFromGross($grossPerDisplay, $rate), 2);

                if (abs($netPerDisplay * $displayQty - $actualNet) <= 0.02) {
                    return [
                        'net' => $netPerDisplay,
                        'gross' => $grossPerDisplay,
                        'displayUnit' => $displayUnit,
                        'factor' => (float) $overrideScale,
                    ];
                }
            }

            return [
                'net' => round($actualNet / $displayQty, 4),
                'gross' => round($actualGross / $displayQty, 4),
                'displayUnit' => $displayUnit,
                'factor' => (float) $overrideScale,
            ];
        }

        if ($product?->unit !== null && (float) $product->unit_price > 0) {
            $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
            // "100 g" is priced per kg on the documents (shelf-price style).
            if ($displayUnit === '100g') {
                $displayUnit = 'kg';
            }
            $factor = max(1, $product->unit->canonicalUnitsPerDisplay($displayUnit));
            $displayQty = $qty / $factor;

            $rate = (int) $item->vat_rate_snapshot_percent;
            $grossPerDisplay = round((float) $product->unit_price * $factor, 2);
            $netPerDisplay = round($grossPerDisplay - ShopOrderService::vatAmountFromGross($grossPerDisplay, $rate), 2);

            // Clean list price only when it reconciles with the charged total
            // within rounding; otherwise the line was repriced — show its rate.
            if ($displayQty > 0 && abs($netPerDisplay * $displayQty - $actualNet) <= 0.02) {
                return [
                    'net' => $netPerDisplay,
                    'gross' => $grossPerDisplay,
                    'displayUnit' => $displayUnit,
                    'factor' => (float) $factor,
                ];
            }

            return [
                'net' => $displayQty > 0 ? round($actualNet / $displayQty, 4) : 0.0,
                'gross' => $displayQty > 0 ? round($actualGross / $displayQty, 4) : 0.0,
                'displayUnit' => $displayUnit,
                'factor' => (float) $factor,
            ];
        }

        // Custom line (no product): keep today's total-derived effective rate.
        $customUnit = (string) ($item->unit ?? '');
        $factor = in_array($customUnit, ['g', 'ml'], true) ? 1000 : 1;
        $displayQty = $qty / $factor;

        return [
            'net' => $displayQty > 0 ? round($actualNet / $displayQty, 4) : 0.0,
            'gross' => $displayQty > 0 ? round($actualGross / $displayQty, 4) : 0.0,
            'displayUnit' => $customUnit,
            'factor' => (float) $factor,
        ];
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
