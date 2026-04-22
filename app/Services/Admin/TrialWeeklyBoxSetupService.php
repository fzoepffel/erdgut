<?php

namespace App\Services\Admin;

use App\Enums\BoxStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Support\BoxValueRecalculationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trial order weekly setup using the same allocation rules as subscription boxes,
 * but targeting a selected list of trial orders for a chosen inventory week.
 *
 * Inventory is shared with subscription boxes via ProductWeekStock and all box_items
 * assigned to boxes for that week_start_date (trial + subscription).
 */
final class TrialWeeklyBoxSetupService
{
    public function __construct(
        private readonly BoxValueRecalculationService $valueRecalculationService,
    ) {}

    /**
     * @param  list<int>  $orderIds
     * @return array{
     *     week_start_date: string,
     *     margin_rate: float,
     *     has_existing: bool,
     *     size_blocks: list<array<string, mixed>>,
     *     stock: list<array<string, mixed>>,
     * }
     */
    public function buildInitialDistribution(Carbon $week, array $orderIds): array
    {
        $week = $week->copy()->startOfDay();
        $marginRate = $this->marginRate();

        $orders = $this->resolveEligibleOrders($orderIds);
        $ordersBySize = $orders->groupBy(fn (Order $o): string => (string) $o->box_size);

        $stockRows = $this->loadStockRows($week);
        $sizeConfigs = $this->sizeConfigsFromOrders($ordersBySize, $marginRate);

        $existingBlocks = $this->loadExistingSizeBlocks($week, $ordersBySize, $sizeConfigs, $stockRows);
        $sizesWithExisting = [];
        foreach ($existingBlocks as $block) {
            $sizesWithExisting[$block['box_size']] = true;
        }

        $autoConfigs = array_filter(
            $sizeConfigs,
            fn (array $_cfg, string $size) => ! isset($sizesWithExisting[$size]),
            ARRAY_FILTER_USE_BOTH
        );
        $autoBlocks = $autoConfigs === []
            ? []
            : $this->allocateProducts($autoConfigs, $stockRows);

        $mergedBlocks = [];
        foreach (array_keys($sizeConfigs) as $size) {
            foreach ($existingBlocks as $block) {
                if ($block['box_size'] === $size) {
                    $mergedBlocks[] = $block;

                    continue 2;
                }
            }
            foreach ($autoBlocks as $block) {
                if ($block['box_size'] === $size) {
                    $mergedBlocks[] = $block + ['from_existing' => false];

                    continue 2;
                }
            }
        }

        return [
            'week_start_date' => $week->toDateString(),
            'margin_rate' => $marginRate,
            'has_existing' => $existingBlocks !== [],
            'size_blocks' => $mergedBlocks,
            'stock' => $this->buildStockSummary($stockRows, $mergedBlocks, $sizeConfigs),
        ];
    }

    /**
     * @param  list<int>  $orderIds
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $sizes
     * @return int Number of orders updated.
     */
    public function applyDistribution(Carbon $week, array $orderIds, array $sizes): int
    {
        $week = $week->copy()->startOfDay();
        $orders = $this->resolveEligibleOrders($orderIds);

        if ($orders->isEmpty()) {
            return 0;
        }

        $this->logIfOverPlanned($week, $orders, $sizes);

        $updated = 0;

        DB::transaction(function () use ($week, $orders, $sizes, &$updated): void {
            foreach ($orders as $order) {
                $size = (string) $order->box_size;
                $linesPayload = $sizes[$size] ?? [];

                $box = Box::query()
                    ->where('order_id', $order->id)
                    ->whereDate('week_start_date', $week->toDateString())
                    ->first();

                $skippedNotEditable = $box !== null && $box->status !== BoxStatus::Editable;
                if ($skippedNotEditable) {
                    continue;
                }

                $box = $this->ensureTrialBox($order, $week);
                if ($box->status !== BoxStatus::Editable) {
                    continue;
                }

                $resolved = $this->resolveLines($linesPayload);

                $box->items()->where('is_extra', false)->delete();

                foreach ($resolved as $line) {
                    /** @var Product $product */
                    $product = $line['product'];
                    BoxItem::create([
                        'box_id' => $box->id,
                        'product_id' => $product->id,
                        'quantity' => (float) $line['quantity'],
                        'unit' => (string) $line['unit'],
                        'is_extra' => false,
                        'is_test' => (bool) $order->is_test,
                    ]);
                }

                $this->valueRecalculationService->recalculate($box->fresh());
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * @param  list<int>  $orderIds
     * @return EloquentCollection<int, Order>
     */
    private function resolveEligibleOrders(array $orderIds): EloquentCollection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return new EloquentCollection;
        }

        return Order::query()
            ->whereIn('id', $ids)
            ->where('product_type', OrderProductType::TrialBox->value)
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Scheduled->value,
            ])
            ->orderBy('id')
            ->get();
    }

    private function ensureTrialBox(Order $order, Carbon $weekStart): Box
    {
        $existing = Box::query()
            ->where('order_id', $order->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        if ($existing instanceof Box) {
            return $existing;
        }

        $box = Box::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $weekStart->copy()->subDays(2),
            'estimated_value' => 0,
            'is_test' => (bool) $order->is_test,
        ]);

        $order->forceFill(['box_id' => $box->id])->save();

        return $box;
    }

    private function marginRate(): float
    {
        $rate = (float) config('box.admin_planning_margin_rate', 0);
        if ($rate < 0) {
            return 0.0;
        }
        if ($rate >= 1) {
            return 0.0;
        }

        return $rate;
    }

    /**
     * @param  Collection<string, EloquentCollection<int, Order>>  $ordersBySize
     * @return array<string, array{n: int, ttc: float, target: float, max: float}>
     */
    private function sizeConfigsFromOrders(Collection $ordersBySize, float $marginRate): array
    {
        $configured = array_keys(config('box.included_value_by_size', []));
        $configs = [];
        foreach ($configured as $size) {
            $n = $ordersBySize->get($size)?->count() ?? 0;
            if ($n === 0) {
                continue;
            }
            $ttc = round((float) config('box.included_value_by_size.'.$size, 0), 2);
            $target = round($ttc * (1 - $marginRate), 2);
            $configs[$size] = [
                'n' => $n,
                'ttc' => $ttc,
                'target' => $target,
                'max' => $ttc,
            ];
        }

        return $configs;
    }

    /**
     * @return array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string, missing_stock?: bool}>
     */
    private function loadStockRows(Carbon $week): array
    {
        $week = $week->copy()->startOfDay();
        $distributed = $this->loadDistributedQuantities($week);

        $rows = ProductWeekStock::query()
            ->whereDate('week_start_date', $week->toDateString())
            ->where('planned_quantity', '>', 0)
            ->with('product')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var Product|null $product */
            $product = $row->product;
            if ($product === null) {
                continue;
            }
            if (! (bool) $product->is_available || ! (bool) $product->is_published) {
                continue;
            }
            $planned = (float) $row->planned_quantity;
            $used = (float) ($distributed[(int) $product->id] ?? 0.0);
            $out[(int) $product->id] = [
                'product' => $product,
                'planned' => $planned,
                'distributed' => round($used, 2),
                'available' => max(0.0, round($planned - $used, 2)),
                'unit' => (string) ($row->unit ?? $product->metadata['unit'] ?? 'piece'),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, float> product_id => distributed quantity in boxes for that week.
     */
    private function loadDistributedQuantities(Carbon $week): array
    {
        $date = $week->copy()->startOfDay()->toDateString();

        $rows = DB::table('box_items')
            ->join('boxes', 'boxes.id', '=', 'box_items.box_id')
            ->selectRaw('box_items.product_id as product_id, SUM(box_items.quantity) as q')
            ->whereDate('boxes.week_start_date', $date)
            ->groupBy('box_items.product_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row->product_id ?? 0);
            if ($pid < 1) {
                continue;
            }
            $out[$pid] = (float) ($row->q ?? 0);
        }

        return $out;
    }

    /**
     * Build per-size blocks from the lowest-id existing editable trial box's non-extra items,
     * for each size that has such a box among the selected orders.
     *
     * @param  Collection<string, EloquentCollection<int, Order>>  $ordersBySize
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @param  array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string, missing_stock?: bool}>  $stockRows
     * @return list<array<string, mixed>>
     */
    private function loadExistingSizeBlocks(
        Carbon $week,
        Collection $ordersBySize,
        array $sizeConfigs,
        array &$stockRows,
    ): array {
        $orderIdsBySize = [];
        foreach ($ordersBySize as $size => $orders) {
            foreach ($orders as $order) {
                $orderIdsBySize[(int) $order->id] = (string) $size;
            }
        }
        if ($orderIdsBySize === []) {
            return [];
        }

        $boxes = Box::query()
            ->forWeek($week->toDateString())
            ->where('status', BoxStatus::Editable)
            ->whereIn('order_id', array_keys($orderIdsBySize))
            ->with([
                'items' => fn ($q) => $q->where('is_extra', false)
                    ->with([
                        'product:id,category,unit_price,metadata,is_available,is_published',
                        'product.translations',
                    ]),
            ])
            ->orderBy('id')
            ->get();

        if ($boxes->isEmpty()) {
            return [];
        }

        $representativeBySize = [];
        foreach ($boxes as $box) {
            $size = $orderIdsBySize[(int) $box->order_id] ?? null;
            if ($size === null) {
                continue;
            }
            if (isset($representativeBySize[$size])) {
                continue;
            }
            $representativeBySize[$size] = $box;
        }

        $blocks = [];
        foreach ($sizeConfigs as $size => $cfg) {
            $box = $representativeBySize[$size] ?? null;
            if ($box === null) {
                continue;
            }
            $lines = [];
            $retailSum = 0.0;
            foreach ($box->items as $item) {
                $product = $item->product;
                if ($product === null) {
                    continue;
                }
                $pid = (int) $product->id;
                $unitPrice = (float) $product->unit_price;
                $qty = (int) max(1, (int) round((float) $item->quantity));
                $unit = (string) ($item->unit ?: ($product->metadata['unit'] ?? 'piece'));

                if (! isset($stockRows[$pid])) {
                    $stockRows[$pid] = [
                        'product' => $product,
                        'planned' => 0.0,
                        'distributed' => 0.0,
                        'available' => 0.0,
                        'unit' => $unit,
                        'missing_stock' => true,
                    ];
                }

                $lines[] = [
                    'product_id' => $pid,
                    'name' => (string) $product->name,
                    'unit' => $unit,
                    'unit_price' => round($unitPrice, 2),
                    'quantity' => $qty,
                ];
                $retailSum += $unitPrice * $qty;
            }
            usort($lines, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

            $blocks[] = [
                'box_size' => (string) $size,
                'n' => $cfg['n'],
                'ttc' => $cfg['ttc'],
                'target' => $cfg['target'],
                'max' => $cfg['max'],
                'retail_sum' => round($retailSum, 2),
                'over_target' => $retailSum > $cfg['max'] + 0.005,
                'from_existing' => true,
                'lines' => $lines,
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @param  array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string}>  $stockRows
     * @return list<array<string, mixed>>
     */
    private function allocateProducts(array $sizeConfigs, array $stockRows): array
    {
        $sizeOrder = array_keys($sizeConfigs);
        usort($sizeOrder, fn (string $a, string $b) => $sizeConfigs[$b]['ttc'] <=> $sizeConfigs[$a]['ttc']);

        $weights = [];
        $weightSum = 0.0;
        foreach ($sizeConfigs as $size => $cfg) {
            $w = $cfg['ttc'] * $cfg['n'];
            $weights[$size] = $w;
            $weightSum += $w;
        }
        $weightSum = $weightSum > 0 ? $weightSum : 1.0;

        $perBoxQuota = [];
        foreach ($stockRows as $pid => $row) {
            $planned = (float) ($row['available'] ?? 0.0);
            foreach ($sizeConfigs as $size => $cfg) {
                $share = $weights[$size] / $weightSum;
                $perBox = (int) floor($planned * $share / max($cfg['n'], 1));
                $perBoxQuota[$pid][$size] = max(0, $perBox);
            }
        }

        $orderedPids = $this->buildCanonicalProductOrder($stockRows);

        $sizeLines = [];
        $sizeRetail = [];
        foreach ($sizeOrder as $size) {
            $sizeLines[$size] = [];
            $sizeRetail[$size] = 0.0;
        }

        foreach ($sizeOrder as $size) {
            $cfg = $sizeConfigs[$size];
            while (true) {
                if ($sizeRetail[$size] >= $cfg['target']) {
                    break;
                }
                $addedThisPass = 0;
                foreach ($orderedPids as $pid) {
                    if ($sizeRetail[$size] >= $cfg['target']) {
                        break;
                    }
                    $quota = $perBoxQuota[$pid][$size] ?? 0;
                    if ($quota <= 0) {
                        continue;
                    }
                    $row = $stockRows[$pid] ?? null;
                    if ($row === null) {
                        continue;
                    }
                    $price = (float) $row['product']->unit_price;
                    if ($price <= 0) {
                        continue;
                    }
                    if ($sizeRetail[$size] + $price > $cfg['target'] + 0.005) {
                        continue;
                    }
                    $sizeLines[$size][$pid] = ($sizeLines[$size][$pid] ?? 0) + 1;
                    $sizeRetail[$size] += $price;
                    $perBoxQuota[$pid][$size] -= 1;
                    $addedThisPass++;
                }
                if ($addedThisPass === 0) {
                    break;
                }
            }
        }

        $blocks = [];
        foreach (array_keys($sizeConfigs) as $size) {
            $cfg = $sizeConfigs[$size];
            $lines = [];
            foreach ($sizeLines[$size] ?? [] as $pid => $qty) {
                $row = $stockRows[$pid];
                /** @var Product $product */
                $product = $row['product'];
                $lines[] = [
                    'product_id' => (int) $pid,
                    'name' => (string) $product->name,
                    'unit' => (string) ($row['unit'] ?: 'piece'),
                    'unit_price' => round((float) $product->unit_price, 2),
                    'quantity' => (int) $qty,
                ];
            }
            usort($lines, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

            $blocks[] = [
                'box_size' => (string) $size,
                'n' => $cfg['n'],
                'ttc' => $cfg['ttc'],
                'target' => $cfg['target'],
                'max' => $cfg['max'],
                'retail_sum' => round($sizeRetail[$size], 2),
                'over_target' => $sizeRetail[$size] > $cfg['max'] + 0.005,
                'lines' => $lines,
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string}>  $stockRows
     * @return list<int>
     */
    private function buildCanonicalProductOrder(array $stockRows): array
    {
        $baseOrder = array_keys((array) config('box.composition_by_size.small', []));
        if ($baseOrder === []) {
            $baseOrder = ['vegetables', 'fruit', 'dairy', 'bakery'];
        }
        $baseIndex = array_flip($baseOrder);

        $seenCategories = [];
        foreach ($stockRows as $row) {
            $seenCategories[(string) $row['product']->category] = true;
        }
        $unknown = array_diff(array_keys($seenCategories), $baseOrder);
        sort($unknown);
        $categoryOrder = array_merge($baseOrder, $unknown);
        $categoryIndex = array_flip($categoryOrder);

        $rows = array_values($stockRows);
        usort($rows, function (array $a, array $b) use ($categoryIndex, $baseIndex) {
            $catA = (string) $a['product']->category;
            $catB = (string) $b['product']->category;
            $ia = $categoryIndex[$catA] ?? (count($baseIndex) + 999);
            $ib = $categoryIndex[$catB] ?? (count($baseIndex) + 999);
            if ($ia !== $ib) {
                return $ia <=> $ib;
            }
            $priceCmp = (float) $b['product']->unit_price <=> (float) $a['product']->unit_price;
            if ($priceCmp !== 0) {
                return $priceCmp;
            }

            return (int) $a['product']->id <=> (int) $b['product']->id;
        });

        return array_map(fn (array $row) => (int) $row['product']->id, $rows);
    }

    /**
     * @param  array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string}>  $stockRows
     * @param  list<array<string, mixed>>  $sizeBlocks
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @return list<array{product_id: int, name: string, unit: string, unit_price: float, planned: float, baseline_used: float, used_total: float, remaining: float}>
     */
    private function buildStockSummary(array $stockRows, array $sizeBlocks, array $sizeConfigs): array
    {
        $used = [];
        foreach ($sizeBlocks as $block) {
            $n = $sizeConfigs[$block['box_size']]['n'] ?? 0;
            foreach ($block['lines'] as $line) {
                $pid = (int) $line['product_id'];
                $qty = (float) $line['quantity'];
                $used[$pid] = ($used[$pid] ?? 0.0) + $qty * $n;
            }
        }

        $out = [];
        foreach ($stockRows as $pid => $row) {
            /** @var Product $product */
            $product = $row['product'];
            $planned = (float) $row['planned'];
            $baseline = (float) ($row['distributed'] ?? 0.0);
            $proposed = (float) ($used[$pid] ?? 0.0);
            $u = round($baseline + $proposed, 2);
            $out[] = [
                'product_id' => (int) $pid,
                'name' => (string) $product->name,
                'unit' => (string) $row['unit'],
                'unit_price' => round((float) $product->unit_price, 2),
                'planned' => round($planned, 2),
                'baseline_used' => round($baseline, 2),
                'used_total' => $u,
                'remaining' => round($planned - $u, 2),
                'missing_stock' => (bool) ($row['missing_stock'] ?? false),
            ];
        }
        usort($out, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @param  list<array{product_id: int, quantity: float}>  $linesPayload
     * @return list<array{product: Product, quantity: float, unit: string}>
     */
    private function resolveLines(array $linesPayload): array
    {
        $out = [];
        foreach ($linesPayload as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);
            if ($pid < 1 || $qty < 0.01) {
                continue;
            }
            $product = Product::query()->visibleInCatalog()->find($pid);
            if ($product === null) {
                continue;
            }
            $out[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit' => (string) ($product->metadata['unit'] ?? 'piece'),
            ];
        }

        return $out;
    }

    /**
     * Soft warning when submitted distribution exceeds remaining planned stock for a product.
     *
     * @param  EloquentCollection<int, Order>  $orders
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $sizes
     */
    private function logIfOverPlanned(Carbon $week, EloquentCollection $orders, array $sizes): void
    {
        $ordersBySize = $orders->groupBy(fn (Order $o): string => (string) $o->box_size);

        $baselineDistributed = $this->loadDistributedQuantities($week);

        $totalsByProduct = [];
        foreach ($sizes as $size => $lines) {
            $n = $ordersBySize->get((string) $size)?->count() ?? 0;
            if ($n <= 0) {
                continue;
            }
            foreach ($lines as $line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                if ($pid < 1 || $qty <= 0) {
                    continue;
                }
                $totalsByProduct[$pid] = ($totalsByProduct[$pid] ?? 0.0) + $qty * $n;
            }
        }

        foreach ($totalsByProduct as $pid => $needed) {
            $stock = ProductWeekStock::query()
                ->where('product_id', $pid)
                ->whereDate('week_start_date', $week->toDateString())
                ->first();
            $planned = $stock !== null ? (float) $stock->planned_quantity : null;
            $already = (float) ($baselineDistributed[(int) $pid] ?? 0.0);
            $total = $already + $needed;
            if ($planned !== null && $total > $planned + 0.005) {
                Log::warning('admin_trial_box_setup.over_planned', [
                    'week_start_date' => $week->toDateString(),
                    'product_id' => $pid,
                    'planned' => $planned,
                    'already_distributed' => round($already, 2),
                    'needed' => round($needed, 2),
                    'total_after_apply' => round($total, 2),
                ]);
            }
        }
    }
}
