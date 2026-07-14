<?php

namespace App\Exports;

use App\Enums\ProductUnit;
use App\Exports\Concerns\FormatsShopOrders;
use App\Models\Producer;
use App\Models\RestaurantOrder;
use App\Models\ShopOrder;
use App\Services\Admin\ProducerAllocationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Aggregated supply view: total quantity needed per producer × product across
 * the filtered orders, B2C and B2B combined. Includes procurement cost +
 * margin estimates so purchasing can sanity-check the order against the
 * buying prices on file. Lines without a producer are grouped under a
 * "no producer" bucket. The revenue column is uniformly gross:
 * `line_total_eur` is gross on both order models.
 */
class ShopOrderFarmTotalsSheet implements FromArray, WithHeadings, WithTitle
{
    use FormatsShopOrders;

    /**
     * @param  Collection<int, ShopOrder>  $orders
     * @param  Collection<int, RestaurantOrder>  $restaurantOrders
     */
    public function __construct(
        private readonly Collection $orders,
        private readonly Collection $restaurantOrders = new Collection,
    ) {}

    public function title(): string
    {
        return (string) __('shop.export.sheet_farms');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            (string) __('shop.export.col_producer'),
            (string) __('shop.export.col_product'),
            (string) __('shop.export.col_quantity'),
            (string) __('shop.export.col_unit'),
            (string) __('shop.export.col_order_count'),
            (string) __('shop.export.col_customer_count'),
            (string) __('shop.export.col_buying_price_per_unit_eur'),
            (string) __('shop.export.col_buying_total_eur'),
            (string) __('shop.export.col_revenue_eur'),
            (string) __('shop.export.col_margin_eur'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        /**
         * Bucket keyed by product identity (`p{id}|{vkey}||display`, custom
         * lines by name). The producer COLUMN is the allocation summary:
         * one farm's name, or "Farm A 12 kg · Farm B 3 kg" when the demand
         * splits across farms. Revenue/margin/counts stay per product
         * bucket. Order tokens are namespaced (`s{id}` / `r{id}`) so a shop
         * order and a restaurant order sharing a numeric id count as two.
         *
         * @var array<string, array{producer:string, product:string, qty_canonical:float, unit:?ProductUnit, display:string, unit_label_override:string, order_ids:array<string,true>, emails:array<string,true>, buying_per_unit:?float, revenue:float}>
         */
        $buckets = [];
        $demand = [];
        $dates = [];

        $collectDemand = function ($order, $item) use (&$demand, &$dates): void {
            if ($item->product_id === null) {
                return;
            }
            $iso = $order->expected_delivery_date?->toDateString();
            if ($iso === null) {
                return;
            }
            $pid = (int) $item->product_id;
            $vkey = (int) ($item->variant_id ?? 0);
            $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + (float) $item->quantity;
            $dates[$iso] = true;
        };

        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $this->accumulate($buckets, $item, 's'.$order->id, $order->email);
                $collectDemand($order, $item);
            }
        }

        // B2B demand: only lines that will actually ship (declined lines and
        // not-yet-accepted request lines are excluded).
        foreach ($this->restaurantOrders as $order) {
            foreach ($order->items as $item) {
                if (! $item->isConfirmedLine()) {
                    continue;
                }
                $this->accumulate($buckets, $item, 'r'.$order->id, $order->user?->email);
                $collectDemand($order, $item);
            }
        }

        $this->applyProducerAttribution($buckets, $demand, array_keys($dates));

        $rows = [];
        foreach ($buckets as $bucket) {
            $unit = $bucket['unit'];
            $display = $bucket['display'];
            if ($unit instanceof ProductUnit && $display !== '') {
                $factor = $unit->canonicalUnitsPerDisplay($display);
                $qty = $factor > 0 ? $bucket['qty_canonical'] / $factor : $bucket['qty_canonical'];
                $unitLabel = ProductUnit::displaySymbol($display);
            } else {
                $qty = $bucket['qty_canonical'];
                $unitLabel = $bucket['unit_label_override'];
            }

            $buyingPerUnit = $bucket['buying_per_unit'];
            // Attribution may have costed the bucket per farm share (per-farm
            // buying prices); buckets without shares keep the default math.
            $buyingTotal = array_key_exists('buying_total', $bucket)
                ? $bucket['buying_total']
                : ($buyingPerUnit !== null ? round($buyingPerUnit * $qty, 2) : null);
            $revenue = round($bucket['revenue'], 2);
            $margin = $buyingTotal !== null ? round($revenue - $buyingTotal, 2) : null;

            $rows[] = [
                $bucket['producer'],
                $bucket['product'],
                round($qty, 4),
                $unitLabel,
                count($bucket['order_ids']),
                count($bucket['emails']),
                $buyingPerUnit,
                $buyingTotal,
                $revenue,
                $margin,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a[0], $a[1]] <=> [$b[0], $b[1]];
        });

        return $rows;
    }

    /**
     * Fold one order line (shop or restaurant) into the shared buckets.
     * Custom lines (no product) carry their free-text unit as the label.
     *
     * @param  array<string, array<string, mixed>>  $buckets
     */
    private function accumulate(array &$buckets, object $item, string $orderToken, ?string $email): void
    {
        $product = $item->product;
        $producerName = $product?->producer?->name
            ?: (string) __('shop.export.no_producer');
        $productName = $product?->name
            ?? ($item->displayName() ?: (string) __('shop.admin.deleted_product'));
        $unit = $product?->unit;
        $display = $product?->price_display_unit
            ?? $unit?->defaultDisplayUnit()
            ?? ($item->unit ?? '');

        // Product-identity key; the producer label starts as the primary
        // producer and is overwritten by the allocation summary afterwards.
        $key = $product !== null
            ? 'p'.$product->id.'|'.((int) ($item->variant_id ?? 0)).'||'.$display
            : 'c'.$productName.'||'.$display;
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'producer' => $producerName,
                'product' => $productName,
                'qty_canonical' => 0.0,
                'unit' => $unit,
                'display' => $display,
                'unit_label_override' => $unit === null && $display !== '' ? ProductUnit::displaySymbol($display) : '',
                'order_ids' => [],
                'emails' => [],
                'buying_per_unit' => $this->buyingPricePerDisplayUnit($product),
                'revenue' => 0.0,
            ];
        }

        $buckets[$key]['qty_canonical'] += (float) $item->quantity;
        $buckets[$key]['order_ids'][$orderToken] = true;
        if ($email !== null && $email !== '') {
            $buckets[$key]['emails'][strtolower($email)] = true;
        }
        $buckets[$key]['revenue'] += (float) $item->line_total_eur;
    }

    /**
     * Overwrite each catalog bucket's producer label with the allocation
     * summary: the single farm's name, or "Farm A 12 kg · Farm B 3 kg"
     * when the demand splits (shares summed over the sheet's date span,
     * uncovered demand attributed to the primary producer).
     *
     * @param  array<string, array<string, mixed>>  $buckets
     * @param  array<int, array<int, array<string, float>>>  $demand
     * @param  list<string>  $dates
     */
    private function applyProducerAttribution(array &$buckets, array $demand, array $dates): void
    {
        if ($demand === [] || $dates === []) {
            return;
        }

        $allocation = app(ProducerAllocationService::class)
            ->allocate(array_keys($demand), $dates, $demand);

        // Sum shares per (pid, vkey, producer) across the span.
        $totals = [];
        foreach ($allocation as $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    foreach ($shares as $producerId => $qty) {
                        $totals[$pid][$vkey][$producerId] = ($totals[$pid][$vkey][$producerId] ?? 0.0) + $qty;
                    }
                }
            }
        }

        $producerIds = [];
        foreach ($totals as $byVkey) {
            foreach ($byVkey as $shares) {
                foreach (array_keys($shares) as $id) {
                    if ($id > 0) {
                        $producerIds[$id] = true;
                    }
                }
            }
        }
        $names = Producer::query()->with('translations')
            ->whereIn('id', array_keys($producerIds))->get()
            ->mapWithKeys(fn ($p) => [$p->id => (string) $p->name]);

        // Per-farm buying price overrides (display-unit cents), one query.
        $pivotPrices = [];
        $pivotRows = DB::table('product_producer')
            ->whereIn('product_id', array_keys($totals))
            ->whereNotNull('buying_price_cents')
            ->get(['product_id', 'producer_id', 'buying_price_cents']);
        foreach ($pivotRows as $row) {
            $pivotPrices[(int) $row->product_id][(int) $row->producer_id] = (int) $row->buying_price_cents;
        }

        // Grade-level overrides: [pid][vkey][producerId] => cents.
        $variantPrices = [];
        $variantRows = DB::table('product_producer_variant_prices')
            ->whereIn('product_id', array_keys($totals))
            ->get(['product_id', 'producer_id', 'variant_id', 'buying_price_cents']);
        foreach ($variantRows as $row) {
            $variantPrices[(int) $row->product_id][(int) $row->variant_id][(int) $row->producer_id] = (int) $row->buying_price_cents;
        }

        foreach ($buckets as $key => $bucket) {
            if (! str_starts_with($key, 'p')) {
                continue; // custom lines keep the no-producer label
            }
            [$identity] = explode('||', $key, 2);
            [$pid, $vkey] = array_map('intval', explode('|', substr($identity, 1)));
            $shares = array_filter($totals[$pid][$vkey] ?? [], fn (float $q): bool => $q > 1e-9);
            if ($shares === []) {
                continue; // keep primary-producer label
            }
            arsort($shares);

            $unit = $bucket['unit'];
            $display = $bucket['display'];
            $factor = ($unit instanceof ProductUnit && $display !== '') ? $unit->canonicalUnitsPerDisplay($display) : 1;
            $symbol = $display !== '' ? ProductUnit::displaySymbol($display) : '';

            // Cost each farm share at that farm's effective buying price
            // (pivot override, else the product default). Any share
            // without an effective price keeps the bucket unpriced, as
            // today. In the single-farm/default-only state the weighted
            // per-unit equals the default exactly.
            $defaultPerUnit = $bucket['buying_per_unit'];
            $costTotal = 0.0;
            $qtyDisplayTotal = 0.0;
            $priced = true;
            foreach ($shares as $producerId => $qtyCanonical) {
                $qtyDisplay = $factor > 0 ? $qtyCanonical / $factor : $qtyCanonical;
                $cents = $producerId > 0
                    ? ($variantPrices[$pid][$vkey][$producerId] ?? $pivotPrices[$pid][$producerId] ?? null)
                    : null;
                $price = $cents !== null ? round($cents / 100, 4) : $defaultPerUnit;
                if ($price === null) {
                    $priced = false;
                    break;
                }
                $costTotal += $qtyDisplay * $price;
                $qtyDisplayTotal += $qtyDisplay;
            }
            if ($priced && $qtyDisplayTotal > 1e-9) {
                $buckets[$key]['buying_total'] = round($costTotal, 2);
                $buckets[$key]['buying_per_unit'] = round($costTotal / $qtyDisplayTotal, 4);
            }

            if (count($shares) === 1) {
                $only = array_key_first($shares);
                $buckets[$key]['producer'] = $only > 0
                    ? (string) ($names[$only] ?? $bucket['producer'])
                    : (string) __('shop.export.no_producer');

                continue;
            }

            $parts = [];
            foreach ($shares as $producerId => $qty) {
                $label = $producerId > 0 ? (string) ($names[$producerId] ?? '') : (string) __('shop.export.no_producer');
                $qtyDisplay = rtrim(rtrim(number_format($factor > 0 ? $qty / $factor : $qty, 2, ',', '.'), '0'), ',');
                $parts[] = trim($label.' '.$qtyDisplay.' '.$symbol);
            }
            $buckets[$key]['producer'] = implode(' · ', $parts);
        }
    }
}
