<?php

namespace App\Services\Admin;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantVerificationStatus;
use App\Enums\ShopOrderStatus;
use App\Exports\Concerns\FormatsShopOrders;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the operations dashboard: weekly NET revenue for the two active
 * channels (B2C shop, B2B restaurants), top-sold products, and operational
 * KPIs. Revenue is attributed by expected_delivery_date (materialized weekly
 * B2B orders exist weeks ahead; those future weeks render as "committed").
 *
 * Net derivation per channel:
 *  - B2B: items_total_net_eur is maintained on the order (invoice truth).
 *  - B2C: total_amount_eur (gross) minus the item VAT snapshots minus the
 *    delivery-fee VAT. `discount_eur` carries no VAT split, so discounts are
 *    subtracted at gross value; net is slightly understated on discounted
 *    orders. Accepted approximation, single-farm scale.
 *
 * Week bucketing happens in PHP (no SQL week functions) so MySQL and the
 * SQLite test database agree on ISO weeks.
 */
class AdminOperationsMetrics
{
    use FormatsShopOrders;

    private const PAST_WEEKS = 12; // incl. current

    private const FUTURE_WEEKS = 4;

    private const TOP_PRODUCTS = 8;

    public function snapshot(): AdminOperationsSnapshot
    {
        return Cache::remember('admin.ops.snapshot.v1', 300, fn (): AdminOperationsSnapshot => $this->build());
    }

    public function build(?Carbon $now = null): AdminOperationsSnapshot
    {
        $now = $now ?? now();
        $currentWeekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $rangeStart = $currentWeekStart->copy()->subWeeks(self::PAST_WEEKS - 1);
        $rangeEnd = $currentWeekStart->copy()->addWeeks(self::FUTURE_WEEKS)->endOfWeek(Carbon::SUNDAY);

        [$b2cNetByDate, $b2cGrossByDate, $b2cCountByDate] = $this->b2cByDate($rangeStart, $rangeEnd);
        [$b2bNetByDate, $b2bCountByDate] = $this->b2bByDate($rangeStart, $rangeEnd);

        $buckets = [];
        $weekTotals = []; // weekStart iso => ['net' => .., 'gross' => ..]
        for ($i = 0; $i < self::PAST_WEEKS + self::FUTURE_WEEKS; $i++) {
            $weekStart = $rangeStart->copy()->addWeeks($i);
            $b2c = 0.0;
            $b2cGross = 0.0;
            $b2b = 0.0;
            for ($d = 0; $d < 7; $d++) {
                $iso = $weekStart->copy()->addDays($d)->toDateString();
                $b2c += $b2cNetByDate[$iso] ?? 0.0;
                $b2cGross += $b2cGrossByDate[$iso] ?? 0.0;
                $b2b += $b2bNetByDate[$iso] ?? 0.0;
            }
            $buckets[] = new AdminOpsWeekBucket(
                isoLabel: (string) __('admin.ops.week_label', ['week' => $weekStart->isoWeek()]),
                weekStart: $weekStart->toDateString(),
                b2cNet: round($b2c, 2),
                b2bNet: round($b2b, 2),
                isFuture: $weekStart->gt($currentWeekStart),
            );
            $weekTotals[$weekStart->toDateString()] = ['net' => $b2c + $b2b, 'gross' => $b2cGross + $b2b];
        }

        // This week vs last week.
        $thisWeekKey = $currentWeekStart->toDateString();
        $lastWeekKey = $currentWeekStart->copy()->subWeek()->toDateString();
        $thisWeekNet = round($weekTotals[$thisWeekKey]['net'] ?? 0.0, 2);
        $lastWeekNet = round($weekTotals[$lastWeekKey]['net'] ?? 0.0, 2);
        $deltaPct = $lastWeekNet > 0.0
            ? round(($thisWeekNet - $lastWeekNet) / $lastWeekNet * 100, 1)
            : null;

        $weekDays = collect(range(0, 6))->map(fn (int $d): string => $currentWeekStart->copy()->addDays($d)->toDateString());
        $ordersThisWeekB2c = (int) $weekDays->sum(fn (string $iso): int => $b2cCountByDate[$iso] ?? 0);
        $ordersThisWeekB2b = (int) $weekDays->sum(fn (string $iso): int => $b2bCountByDate[$iso] ?? 0);

        [$topProducts, $margin, $marginCoverage] = $this->productAggregates($now, $currentWeekStart);

        // 30-day average order value per channel (net).
        $aovFrom = $now->copy()->subDays(30)->toDateString();
        $aovTo = $now->copy()->toDateString();
        $b2c30Net = 0.0;
        $b2c30Count = 0;
        foreach ($b2cNetByDate as $iso => $net) {
            if ($iso >= $aovFrom && $iso <= $aovTo) {
                $b2c30Net += $net;
                $b2c30Count += $b2cCountByDate[$iso] ?? 0;
            }
        }
        $b2b30Net = 0.0;
        $b2b30Count = 0;
        foreach ($b2bNetByDate as $iso => $net) {
            if ($iso >= $aovFrom && $iso <= $aovTo) {
                $b2b30Net += $net;
                $b2b30Count += $b2bCountByDate[$iso] ?? 0;
            }
        }

        return new AdminOperationsSnapshot(
            weeklyRevenue: $buckets,
            topProducts: $topProducts,
            revenueThisWeekNet: $thisWeekNet,
            revenueThisWeekGross: round($weekTotals[$thisWeekKey]['gross'] ?? 0.0, 2),
            revenueWeekDeltaPct: $deltaPct,
            ordersThisWeekB2c: $ordersThisWeekB2c,
            ordersThisWeekB2b: $ordersThisWeekB2b,
            openB2bRequests: RestaurantOrder::query()->where('status', RestaurantOrderStatus::Submitted->value)->count(),
            unpaidB2bInvoicesCount: $this->unpaidInvoices()->count(),
            unpaidB2bInvoicesNet: round((float) $this->unpaidInvoices()->sum('items_total_net_eur'), 2),
            outstandingInPersonEur: round((float) ShopOrder::query()
                ->where('status', ShopOrderStatus::PendingPayment->value)
                ->where('payment_method', 'in_person')
                ->sum('total_amount_eur'), 2),
            activeStandingOrders: RestaurantStandingOrder::query()->active()->count(),
            pendingRestaurantApplications: RestaurantProfile::query()
                ->where('verification_status', RestaurantVerificationStatus::Pending->value)
                ->count(),
            avgOrderValueB2c30dNet: $b2c30Count > 0 ? round($b2c30Net / $b2c30Count, 2) : 0.0,
            avgOrderValueB2b30dNet: $b2b30Count > 0 ? round($b2b30Net / $b2b30Count, 2) : 0.0,
            marginThisWeekNet: $margin,
            marginCoveragePct: $marginCoverage,
        );
    }

    /**
     * B2C finalized orders per delivery date: [netByDate, grossByDate, countByDate].
     *
     * @return array{0: array<string, float>, 1: array<string, float>, 2: array<string, int>}
     */
    private function b2cByDate(Carbon $from, Carbon $to): array
    {
        $orders = ShopOrder::query()
            ->finalized()
            ->whereBetween('expected_delivery_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('expected_delivery_date')
            ->selectRaw('expected_delivery_date as d, SUM(total_amount_eur) as gross, SUM(delivery_fee_vat_amount_eur) as fee_vat, COUNT(*) as cnt')
            ->get();

        $itemVat = ShopOrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->where(function ($q): void {
                $q->whereIn('shop_orders.status', [ShopOrderStatus::Paid->value, ShopOrderStatus::Fulfilled->value])
                    ->orWhere(function ($q): void {
                        $q->where('shop_orders.status', ShopOrderStatus::PendingPayment->value)
                            ->where('shop_orders.payment_method', 'in_person');
                    });
            })
            ->whereBetween('shop_orders.expected_delivery_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('shop_orders.expected_delivery_date')
            ->selectRaw('shop_orders.expected_delivery_date as d, SUM(shop_order_items.vat_amount_eur) as vat')
            ->pluck('vat', 'd');

        $net = [];
        $gross = [];
        $count = [];
        foreach ($orders as $row) {
            $iso = Carbon::parse($row->d)->toDateString();
            $gross[$iso] = (float) $row->gross;
            $net[$iso] = (float) $row->gross - (float) ($itemVat[$row->d] ?? 0.0) - (float) $row->fee_vat;
            $count[$iso] = (int) $row->cnt;
        }

        return [$net, $gross, $count];
    }

    /**
     * B2B committed orders per delivery date: [netByDate, countByDate].
     *
     * @return array{0: array<string, float>, 1: array<string, int>}
     */
    private function b2bByDate(Carbon $from, Carbon $to): array
    {
        $rows = RestaurantOrder::query()
            ->whereIn('status', [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value])
            ->whereBetween('expected_delivery_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('expected_delivery_date')
            ->selectRaw('expected_delivery_date as d, SUM(items_total_net_eur) as net, COUNT(*) as cnt')
            ->get();

        $net = [];
        $count = [];
        foreach ($rows as $row) {
            $iso = Carbon::parse($row->d)->toDateString();
            $net[$iso] = (float) $row->net;
            $count[$iso] = (int) $row->cnt;
        }

        return [$net, $count];
    }

    /**
     * Top products over the last 30 days (both channels, shipping lines only)
     * and the current week's estimated margin.
     *
     * @return array{0: list<AdminOpsTopProduct>, 1: ?float, 2: float}
     */
    private function productAggregates(Carbon $now, Carbon $currentWeekStart): array
    {
        $from = $now->copy()->subDays(30)->toDateString();
        $to = $now->copy()->toDateString();
        $weekStartIso = $currentWeekStart->toDateString();
        $weekEndIso = $currentWeekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        // Buckets keyed producer||name||displayUnit, mirroring the farm-totals sheet.
        $buckets = [];
        $grandTotal = 0.0;
        $marginNet = 0.0;
        $marginPricedNet = 0.0;
        $marginTotalNet = 0.0;
        $hasPricedLines = false;

        // Current-week margin is costed AFTER folding: line quantities are
        // aggregated per (delivery day, product, grade) and each aggregate is
        // costed via the producer allocation, so every share pays the price
        // of the farm that covers it (per-farm buying prices). Mirrors the
        // farm-totals sheet (injected-demand mode).
        $weekAgg = [];      // [iso][pid][vkey] => ['qty' => canonical, 'net' => float]
        $weekDemand = [];   // [pid][vkey][iso] => canonical qty (allocation input)
        $weekProducts = []; // [pid] => Product

        $fold = function (object $item, string $deliveryIso) use (&$buckets, &$grandTotal, &$marginTotalNet, &$weekAgg, &$weekDemand, &$weekProducts, $from, $to, $weekStartIso, $weekEndIso): void {
            $lineNet = round((float) $item->line_total_eur - (float) $item->vat_amount_eur, 2);

            // 30d top-product window.
            if ($deliveryIso >= $from && $deliveryIso <= $to) {
                $product = $item->product;
                $producerName = $product?->producer?->name ?: '';
                $productName = $product?->name ?? ($item->displayName() ?: '');
                $unit = $product?->unit;
                $display = $product?->price_display_unit
                    ?? $unit?->defaultDisplayUnit()
                    ?? ($item->unit ?? '');
                // Product-identity bucket: multi-producer products stay ONE
                // row in the top chart; the producer field shows the primary
                // (display only — 30-day allocation would be disproportionate).
                $key = ($product?->id !== null ? 'p'.$product->id : 'c'.$productName).'||'.$display;
                if (! isset($buckets[$key])) {
                    $buckets[$key] = [
                        'name' => $productName,
                        'producer' => $producerName,
                        'qty_canonical' => 0.0,
                        'unit' => $unit,
                        'display' => $display,
                        'net' => 0.0,
                    ];
                }
                $buckets[$key]['qty_canonical'] += (float) $item->quantity;
                $buckets[$key]['net'] += $lineNet;
                $grandTotal += $lineNet;
            }

            // Current-week margin: aggregate now, cost after the fold.
            if ($deliveryIso >= $weekStartIso && $deliveryIso <= $weekEndIso) {
                $marginTotalNet += $lineNet;
                $product = $item->product;
                if ($product !== null) {
                    $vkey = (int) ($item->variant_id ?? 0);
                    $qty = (float) $item->quantity;
                    $agg = &$weekAgg[$deliveryIso][$product->id][$vkey];
                    $agg ??= ['qty' => 0.0, 'net' => 0.0];
                    $agg['qty'] += $qty;
                    $agg['net'] += $lineNet;
                    unset($agg);
                    $weekDemand[$product->id][$vkey][$deliveryIso] = ($weekDemand[$product->id][$vkey][$deliveryIso] ?? 0.0) + $qty;
                    $weekProducts[$product->id] = $product;
                }
                // Product-less custom lines stay unpriced, as before.
            }
        };

        $windowFrom = min($from, $weekStartIso);
        $windowTo = max($to, $weekEndIso);

        ShopOrder::query()
            ->finalized()
            ->whereBetween('expected_delivery_date', [$windowFrom, $windowTo])
            ->with('items.product.producer.translations', 'items.product.translations')
            ->get()
            ->each(function (ShopOrder $order) use ($fold): void {
                $iso = $order->expected_delivery_date?->toDateString() ?? '';
                foreach ($order->items as $item) {
                    $fold($item, $iso);
                }
            });

        RestaurantOrder::query()
            ->whereIn('status', [RestaurantOrderStatus::Confirmed->value, RestaurantOrderStatus::Fulfilled->value])
            ->whereBetween('expected_delivery_date', [$windowFrom, $windowTo])
            ->with('items.product.producer.translations', 'items.product.translations')
            ->get()
            ->each(function (RestaurantOrder $order) use ($fold): void {
                $iso = $order->expected_delivery_date?->toDateString() ?? '';
                foreach ($order->items as $item) {
                    if (! $item->isConfirmedLine()) {
                        continue; // declined or still-pending lines never ship
                    }
                    $fold($item, $iso);
                }
            });

        // Cost the week's aggregates: allocation shares per (day, product,
        // grade), each share at its farm's effective buying price (pivot
        // override, else the product default). Any unpriced share leaves
        // that aggregate out of the margin, matching the old per-line rule.
        if ($weekAgg !== []) {
            $weekDates = array_keys($weekAgg);
            sort($weekDates);
            $allocation = app(ProducerAllocationService::class)
                ->allocate(array_keys($weekDemand), $weekDates, $weekDemand);

            $pivotPrices = [];
            $pivotRows = DB::table('product_producer')
                ->whereIn('product_id', array_keys($weekDemand))
                ->whereNotNull('buying_price_cents')
                ->get(['product_id', 'producer_id', 'buying_price_cents']);
            foreach ($pivotRows as $row) {
                $pivotPrices[(int) $row->product_id][(int) $row->producer_id] = (int) $row->buying_price_cents;
            }

            foreach ($weekAgg as $iso => $byPid) {
                foreach ($byPid as $pid => $byVkey) {
                    foreach ($byVkey as $vkey => $agg) {
                        $product = $weekProducts[$pid];
                        $default = $this->buyingPricePerDisplayUnit($product);
                        $shares = $allocation[$iso][$pid][$vkey] ?? [];

                        $cost = 0.0;
                        $priced = true;
                        if ($shares === []) {
                            // No allocation (zero-qty edge): default costing.
                            if ($default === null) {
                                $priced = false;
                            } else {
                                [$qtyDisplay] = $this->displayQuantity($product->unit, $product->price_display_unit, (float) $agg['qty']);
                                $cost = $qtyDisplay * $default;
                            }
                        }
                        foreach ($shares as $producerId => $qtyCanonical) {
                            $price = ($producerId > 0 && isset($pivotPrices[$pid][$producerId]))
                                ? round($pivotPrices[$pid][$producerId] / 100, 4)
                                : $default;
                            if ($price === null) {
                                $priced = false;
                                break;
                            }
                            [$qtyDisplay] = $this->displayQuantity($product->unit, $product->price_display_unit, (float) $qtyCanonical);
                            $cost += $qtyDisplay * $price;
                        }

                        if ($priced) {
                            $marginNet += $agg['net'] - round($cost, 2);
                            $marginPricedNet += $agg['net'];
                            $hasPricedLines = true;
                        }
                    }
                }
            }
        }

        $top = collect($buckets)
            ->sortByDesc('net')
            ->take(self::TOP_PRODUCTS)
            ->map(function (array $bucket) use ($grandTotal): AdminOpsTopProduct {
                $unit = $bucket['unit'];
                $display = $bucket['display'];
                if ($unit instanceof ProductUnit && $display !== '') {
                    $factor = $unit->canonicalUnitsPerDisplay($display);
                    $qty = $factor > 0 ? $bucket['qty_canonical'] / $factor : $bucket['qty_canonical'];
                    $symbol = ProductUnit::displaySymbol($display);
                } else {
                    $qty = $bucket['qty_canonical'];
                    $symbol = $display !== '' ? ProductUnit::displaySymbol($display) : '';
                }

                return new AdminOpsTopProduct(
                    name: $bucket['name'],
                    producer: $bucket['producer'],
                    qtyDisplay: round($qty, 2),
                    unitSymbol: $symbol,
                    netRevenue: round($bucket['net'], 2),
                    share: $grandTotal > 0.0 ? round($bucket['net'] / $grandTotal, 4) : 0.0,
                );
            })
            ->values()
            ->all();

        $coverage = $marginTotalNet > 0.0 ? round($marginPricedNet / $marginTotalNet * 100, 1) : 0.0;

        return [$top, $hasPricedLines ? round($marginNet, 2) : null, $coverage];
    }

    private function unpaidInvoices(): Builder
    {
        return RestaurantOrder::query()
            ->whereNotNull('invoiced_at')
            ->whereNull('invoice_paid_at');
    }
}
