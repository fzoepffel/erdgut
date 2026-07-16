<?php

namespace App\Console\Commands;

use App\Enums\CustomerType;
use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopOrderStatus;
use App\Models\AuditEvent;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for B2B orders placed BEFORE business prices became net
 * (deployed 2026-07-16 17:03 UTC): back then the maintained NET list
 * values were frozen into the orders as GROSS. This command re-reads each
 * stored gross line total as the net it was meant to be and adds the VAT
 * on top (gross = old × (100 + rate) / 100), for shop orders including
 * the delivery fee.
 *
 * Safety: dry-run by default (--apply to write), only non-invoiced
 * business orders created before the fix, idempotent via a
 * 'b2b_net_reprice' audit event per order. Paid shop orders get their
 * settlement re-opened, so the 7 % difference shows up as an open amount
 * on the order page. Already-invoiced orders are listed for manual fixing
 * in the invoice editor.
 */
class RepriceB2bOrdersNetCommand extends Command
{
    /** When the net-semantics fix went live on production. */
    private const FIX_DEPLOYED_AT = '2026-07-16 17:03:44';

    protected $signature = 'b2b:reprice-net
        {--apply : Write the changes (default is a dry run)}
        {--before= : Only orders created before this datetime (default: the fix deploy)}';

    protected $description = 'Reinterpret pre-fix B2B order amounts as net and add VAT on top (dry-run by default)';

    public function handle(RestaurantInvoiceService $restaurantInvoices, RestaurantOrderService $restaurantOrders): int
    {
        $apply = (bool) $this->option('apply');
        $before = Carbon::parse((string) ($this->option('before') ?: self::FIX_DEPLOYED_AT));

        $this->info(($apply ? 'APPLYING' : 'DRY RUN (nothing is written; use --apply)')
            .' — B2B orders created before '.$before->format('Y-m-d H:i:s').' UTC');

        $rows = [];
        $skippedInvoiced = [];

        // ── Shop orders (customer type business, no mirror) ────────────
        $shopOrders = ShopOrder::query()
            ->where('customer_type', CustomerType::Business->value)
            ->whereNull('restaurant_order_id')
            ->where('created_at', '<', $before)
            ->whereNotIn('status', [ShopOrderStatus::Cancelled->value, ShopOrderStatus::Failed->value, ShopOrderStatus::Refunded->value])
            ->orderBy('id')
            ->get();

        foreach ($shopOrders as $order) {
            if ($order->isInvoiced()) {
                $skippedInvoiced[] = 'Shop-Bestellung #'.$order->id.' (Rechnung '.$order->invoice_number.')';

                continue;
            }
            if ($this->alreadyRepriced('shop_order', (int) $order->id)) {
                continue;
            }

            $newTotal = $this->repriceShopOrder($order, $apply);
            $rows[] = ['Shop #'.$order->id, $order->name, number_format((float) $order->total_amount_eur, 2, ',', '.').' €', number_format($newTotal, 2, ',', '.').' €', $order->paid_at !== null ? 'ja' : 'nein'];
        }

        // ── Restaurant orders (inherently B2B, same wrong snapshots) ───
        $restOrders = RestaurantOrder::query()
            ->where('created_at', '<', $before)
            ->whereNotIn('status', [RestaurantOrderStatus::Cancelled->value, RestaurantOrderStatus::Declined->value])
            ->whereHas('items')
            ->orderBy('id')
            ->get();

        foreach ($restOrders as $order) {
            if ($order->isInvoiced()) {
                $skippedInvoiced[] = 'Restaurant-Bestellung #'.$order->id.' (Rechnung '.$order->invoice_number.')';

                continue;
            }
            if ($this->alreadyRepriced('restaurant_order', (int) $order->id)) {
                continue;
            }

            $newGross = $this->repriceRestaurantOrder($order, $apply, $restaurantInvoices, $restaurantOrders);
            $rows[] = ['Restaurant #'.$order->id, (string) ($order->user?->email ?? ''), number_format((float) $order->items_total_gross_eur, 2, ',', '.').' €', number_format($newGross, 2, ',', '.').' €', '—'];
        }

        if ($rows === []) {
            $this->info('Keine Bestellungen zu korrigieren.');
        } else {
            $this->table(['Bestellung', 'Kundin/Kunde', 'Betrag alt (brutto)', 'Betrag neu (brutto)', 'Bezahlt'], $rows);
            $this->info(count($rows).' Bestellung(en) '.($apply ? 'korrigiert.' : 'würden korrigiert.'));
        }

        if ($skippedInvoiced !== []) {
            $this->warn('Übersprungen, weil schon eine Rechnung gestellt wurde (bitte im Rechnungs-Editor manuell korrigieren):');
            foreach ($skippedInvoiced as $line) {
                $this->line('  - '.$line);
            }
        }

        if (! $apply && $rows !== []) {
            $this->comment('Nichts geschrieben. Zum Ausführen: php artisan b2b:reprice-net --apply');
        }

        return self::SUCCESS;
    }

    private function alreadyRepriced(string $entityType, int $entityId): bool
    {
        return AuditEvent::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('action', 'b2b_net_reprice')
            ->exists();
    }

    /** Returns the new gross total (computed even on dry runs). */
    private function repriceShopOrder(ShopOrder $order, bool $apply): float
    {
        $itemsTotal = 0.0;
        $itemUpdates = [];
        foreach ($order->items as $item) {
            $rate = (int) $item->vat_rate_snapshot_percent;
            $net = (float) $item->line_total_eur; // stored gross WAS the intended net
            $gross = round($net * (100 + $rate) / 100, 2);
            $qty = (float) $item->quantity;
            $itemUpdates[$item->id] = [
                'line_total_eur' => $gross,
                'vat_amount_eur' => round($gross - $net, 2),
                'unit_price_snapshot_eur' => $qty > 0 ? round($gross / $qty, 4) : $gross,
            ];
            $itemsTotal += $gross;
        }
        $itemsTotal = round($itemsTotal, 2);

        $feeNet = (float) $order->delivery_fee_eur; // also entered as net
        $feeRate = (int) ($order->delivery_fee_vat_rate_percent ?? 7);
        $feeGross = $feeNet > 0 ? round($feeNet * (100 + $feeRate) / 100, 2) : 0.0;

        $total = round($itemsTotal - (float) $order->discount_eur + $feeGross, 2);

        if (! $apply) {
            return $total;
        }

        DB::transaction(function () use ($order, $itemUpdates, $itemsTotal, $feeNet, $feeGross, $total): void {
            $oldTotal = (float) $order->total_amount_eur;

            // Legacy snapshot BEFORE the recompute: what was actually paid.
            if ($order->paid_at !== null && $order->paid_amount_eur === null) {
                $order->forceFill(['paid_amount_eur' => $oldTotal])->save();
            }

            foreach ($order->items as $item) {
                $item->forceFill($itemUpdates[$item->id])->save();
            }

            $reopens = $order->paid_at !== null
                && abs($total - (float) $order->paid_amount_eur) >= 0.01;

            $order->forceFill([
                'items_total_eur' => $itemsTotal,
                'delivery_fee_eur' => $feeGross,
                'delivery_fee_vat_amount_eur' => $feeGross > 0 ? round($feeGross - $feeNet, 2) : 0.0,
                'total_amount_eur' => $total,
            ] + ($reopens ? [
                'outstanding_settled_amount_eur' => null,
                'outstanding_settled_at' => null,
                'outstanding_settled_by_user_id' => null,
            ] : []))->save();

            AuditLogger::log(
                entityType: 'shop_order',
                entityId: (int) $order->id,
                action: 'b2b_net_reprice',
                fromState: $order->status->value,
                toState: $order->status->value,
                metadata: ['channel' => 'cli', 'total_before' => $oldTotal, 'total_after' => $total],
            );
        });

        return $total;
    }

    /** Returns the new gross items total (computed even on dry runs). */
    private function repriceRestaurantOrder(
        RestaurantOrder $order,
        bool $apply,
        RestaurantInvoiceService $invoices,
        RestaurantOrderService $orders,
    ): float {
        $newGross = 0.0;
        $itemUpdates = [];
        foreach ($order->items as $item) {
            if ($item->isDeclined()) {
                continue;
            }
            $rate = (int) $item->vat_rate_snapshot_percent;
            $net = (float) $item->line_total_eur;
            $gross = round($net * (100 + $rate) / 100, 2);
            $qty = (float) $item->quantity;
            $itemUpdates[$item->id] = [
                'line_total_eur' => $gross,
                'vat_amount_eur' => round($gross - $net, 2),
                'unit_price_snapshot_eur' => $qty > 0 ? round($gross / $qty, 4) : $gross,
            ];
            $newGross += $gross;
        }
        // The restaurant delivery fee column is ALREADY net; only fold it
        // into the projected gross for the dry-run display.
        $feeNet = (float) ($order->delivery_fee_net_eur ?? 0);
        $feeRate = (int) ($order->delivery_fee_vat_rate_percent ?? 7);
        $newGross = round($newGross + ($feeNet > 0 ? round($feeNet * (100 + $feeRate) / 100, 2) : 0), 2);

        if (! $apply) {
            return $newGross;
        }

        DB::transaction(function () use ($order, $itemUpdates, $invoices, $orders): void {
            $oldGross = (float) $order->items_total_gross_eur;

            foreach ($order->items as $item) {
                if (isset($itemUpdates[$item->id])) {
                    $item->forceFill($itemUpdates[$item->id])->save();
                }
            }

            $invoices->rerollTotals($order);
            $orders->syncShopOrderMirror($order);

            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $order->id,
                action: 'b2b_net_reprice',
                fromState: $order->status->value,
                toState: $order->status->value,
                metadata: ['channel' => 'cli', 'gross_before' => $oldGross, 'gross_after' => (float) $order->refresh()->items_total_gross_eur],
            );
        });

        return (float) $order->items_total_gross_eur;
    }
}
