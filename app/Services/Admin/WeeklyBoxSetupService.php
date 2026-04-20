<?php

namespace App\Services\Admin;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\Subscription;
use App\Support\BoxValueRecalculationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Box setup flow (ERD-NEW): 4-week calendar, weekly auto-distribution and apply.
 *
 * Target per size = TTC × (1 − admin_planning_margin_rate); hard max = TTC.
 * Auto-distribution pulls only products that have a positive ProductWeekStock for the week.
 */
final class WeeklyBoxSetupService
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner,
        private readonly BoxValueRecalculationService $valueRecalculationService,
    ) {}

    /**
     * Overview of the next N Mondays for the calendar.
     *
     * @return list<array{
     *     week_start_date: string,
     *     week: Carbon,
     *     status: string,
     *     eligible_count: int,
     *     boxes_count: int,
     *     total_estimated_value: float,
     *     per_size: array<string, array{eligible: int, boxes: int}>,
     * }>
     */
    public function calendarOverview(?int $weekCount = null): array
    {
        $firstMonday = WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
        $weeks = $this->weeklyBoxPlanner->horizonWeekStarts($firstMonday, $weekCount);

        $rows = [];
        foreach ($weeks as $week) {
            $eligible = $this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($week);
            $eligibleBySize = $eligible->groupBy(fn (Subscription $s): string => (string) $s->box_size);

            $boxes = Box::query()
                ->forWeek($week->toDateString())
                ->get(['id', 'user_id', 'estimated_value']);

            $boxesByUser = $boxes->keyBy('user_id');
            $boxesUsers = $boxes->pluck('user_id')->all();

            $boxesBySize = $eligible
                ->filter(fn (Subscription $s) => in_array($s->user_id, $boxesUsers, true))
                ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

            $perSize = [];
            foreach (array_keys(config('box.included_value_by_size', [])) as $size) {
                $perSize[$size] = [
                    'eligible' => $eligibleBySize->get($size)?->count() ?? 0,
                    'boxes' => $boxesBySize->get($size)?->count() ?? 0,
                ];
            }

            $eligibleCount = $eligible->count();
            $boxesCount = $boxes->count();
            $status = $this->resolveStatus($eligibleCount, $boxesCount);

            $rows[] = [
                'week_start_date' => $week->toDateString(),
                'week' => $week,
                'status' => $status,
                'eligible_count' => $eligibleCount,
                'boxes_count' => $boxesCount,
                'total_estimated_value' => round((float) $boxes->sum(fn (Box $b) => (float) $b->estimated_value), 2),
                'per_size' => $perSize,
            ];
        }

        return $rows;
    }

    private function resolveStatus(int $eligible, int $boxes): string
    {
        if ($eligible === 0 && $boxes === 0) {
            return 'not_set_up';
        }
        if ($boxes === 0) {
            return 'not_set_up';
        }
        if ($boxes < $eligible) {
            return 'partial';
        }

        return 'set_up';
    }

    /**
     * Build the initial distribution payload for the setup dialog.
     *
     * If any editable box already exists for the week, per-size lines are loaded from a
     * representative (lowest-id) box for that size. Sizes without any existing box fall back
     * to the round-robin auto-allocator.
     *
     * @return array{
     *     week_start_date: string,
     *     margin_rate: float,
     *     has_existing: bool,
     *     size_blocks: list<array{
     *         box_size: string,
     *         n: int,
     *         ttc: float,
     *         target: float,
     *         max: float,
     *         retail_sum: float,
     *         over_target: bool,
     *         from_existing: bool,
     *         lines: list<array{product_id: int, name: string, unit: string, unit_price: float, quantity: int}>,
     *     }>,
     *     stock: list<array{
     *         product_id: int,
     *         name: string,
     *         unit: string,
     *         unit_price: float,
     *         planned: float,
     *         used_total: float,
     *         remaining: float,
     *         missing_stock: bool,
     *     }>,
     * }
     */
    public function buildInitialDistribution(Carbon $week): array
    {
        $week = $week->copy()->startOfDay();
        $marginRate = $this->marginRate();

        $subsBySize = $this->weeklyBoxPlanner
            ->eligibleSubscriptionsForWeek($week)
            ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        $stockRows = $this->loadStockRows($week);
        $sizeConfigs = $this->sizeConfigsFromSubs($subsBySize, $marginRate);

        $existingBlocks = $this->loadExistingSizeBlocks($week, $subsBySize, $sizeConfigs, $stockRows);
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
     * Build per-size blocks from the lowest-id existing editable box's non-extra items, for each
     * size that has such a box. Sizes without an existing box are omitted (caller falls back to
     * auto-allocation). Products referenced by those items that are not in the weekly stock rows
     * are appended to $stockRows with planned=0 and a `missing_stock` flag so the UI can flag them.
     *
     * @param  Collection<string, EloquentCollection<int, Subscription>>  $subsBySize
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @param  array<int, array{product: Product, planned: float, unit: string, missing_stock?: bool}>  $stockRows
     * @return list<array<string, mixed>>
     */
    private function loadExistingSizeBlocks(
        Carbon $week,
        Collection $subsBySize,
        array $sizeConfigs,
        array &$stockRows,
    ): array {
        $eligibleUserIds = [];
        foreach ($subsBySize as $size => $subs) {
            foreach ($subs as $sub) {
                $eligibleUserIds[(int) $sub->user_id] = (string) $size;
            }
        }
        if ($eligibleUserIds === []) {
            return [];
        }

        $boxes = Box::query()
            ->forWeek($week->toDateString())
            ->where('status', BoxStatus::Editable)
            ->whereIn('user_id', array_keys($eligibleUserIds))
            ->with([
                'items' => fn ($q) => $q->where('is_extra', false)
                    ->with('product:id,name,category,unit_price,metadata,is_available,is_published'),
            ])
            ->orderBy('id')
            ->get();

        if ($boxes->isEmpty()) {
            return [];
        }

        $representativeBySize = [];
        foreach ($boxes as $box) {
            $size = $eligibleUserIds[(int) $box->user_id] ?? null;
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
     * Build the dialog payload with user-submitted size lines (for post-back rendering).
     *
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $submittedSizes
     * @return array{
     *     week_start_date: string,
     *     margin_rate: float,
     *     size_blocks: list<array<string, mixed>>,
     *     stock: list<array<string, mixed>>,
     * }
     */
    public function buildFromSubmittedLines(Carbon $week, array $submittedSizes): array
    {
        $week = $week->copy()->startOfDay();
        $marginRate = $this->marginRate();

        $subsBySize = $this->weeklyBoxPlanner
            ->eligibleSubscriptionsForWeek($week)
            ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        $stockRows = $this->loadStockRows($week);
        $sizeConfigs = $this->sizeConfigsFromSubs($subsBySize, $marginRate);

        $sizeBlocks = [];
        foreach ($sizeConfigs as $size => $cfg) {
            $lines = [];
            $retailSum = 0.0;
            foreach ($submittedSizes[$size] ?? [] as $line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                $stock = $stockRows[$pid] ?? null;
                if ($pid < 1 || $qty < 1 || $stock === null) {
                    continue;
                }
                /** @var Product $product */
                $product = $stock['product'];
                $unitPrice = (float) $product->unit_price;
                $retailSum += $unitPrice * $qty;
                $lines[] = [
                    'product_id' => $pid,
                    'name' => (string) $product->name,
                    'unit' => (string) ($product->metadata['unit'] ?? 'piece'),
                    'unit_price' => round($unitPrice, 2),
                    'quantity' => $qty,
                ];
            }
            $sizeBlocks[] = [
                'box_size' => $size,
                'n' => $cfg['n'],
                'ttc' => $cfg['ttc'],
                'target' => $cfg['target'],
                'max' => $cfg['max'],
                'retail_sum' => round($retailSum, 2),
                'over_target' => $retailSum > $cfg['max'] + 0.005,
                'lines' => $lines,
            ];
        }

        return [
            'week_start_date' => $week->toDateString(),
            'margin_rate' => $marginRate,
            'size_blocks' => $sizeBlocks,
            'stock' => $this->buildStockSummary($stockRows, $sizeBlocks, $sizeConfigs),
        ];
    }

    /**
     * Apply the submitted distribution: create/update one Box + BoxItems per eligible subscription.
     *
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $sizes
     */
    public function applyDistribution(Carbon $week, array $sizes): int
    {
        $week = $week->copy()->startOfDay();
        $updated = 0;

        $this->logIfOverPlanned($week, $sizes);

        DB::transaction(function () use ($week, $sizes, &$updated): void {
            foreach ($this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($week) as $subscription) {
                $size = (string) $subscription->box_size;
                $linesPayload = $sizes[$size] ?? [];

                $box = Box::query()
                    ->forUser($subscription->user_id)
                    ->forWeek($week->toDateString())
                    ->first();

                $skippedNotEditable = $box !== null && $box->status !== BoxStatus::Editable;
                if ($skippedNotEditable) {
                    continue;
                }

                $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $week);
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
                    ]);
                }

                $this->valueRecalculationService->recalculate($box->fresh());
                $updated++;
            }
        });

        return $updated;
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
     * @param  Collection<string, EloquentCollection<int, Subscription>>  $subsBySize
     * @return array<string, array{n: int, ttc: float, target: float, max: float}>
     */
    private function sizeConfigsFromSubs(Collection $subsBySize, float $marginRate): array
    {
        $configured = array_keys(config('box.included_value_by_size', []));
        $configs = [];
        foreach ($configured as $size) {
            $n = $subsBySize->get($size)?->count() ?? 0;
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
     * @return array<int, array{product: Product, planned: float, unit: string}>
     */
    private function loadStockRows(Carbon $week): array
    {
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
            $out[(int) $product->id] = [
                'product' => $product,
                'planned' => (float) $row->planned_quantity,
                'unit' => (string) ($row->unit ?? $product->metadata['unit'] ?? 'piece'),
            ];
        }

        return $out;
    }

    /**
     * Auto-distribution core (round-robin by category then price).
     *
     * Per product: quota_{p,s} = floor(planned_p × share_s / n_s) per box (share_s = ttc_s × n_s / Σ ttc × n).
     * For each size (largest TTC first): cycle over products in canonical order (category order from
     * config('box.composition_by_size.small') + alphabetical fallback; within category unit_price DESC
     * tiebreak id ASC), adding 1 unit per candidate per pass while it fits under max and has quota.
     * Stop when target reached, or when a full pass adds no units.
     *
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @param  array<int, array{product: Product, planned: float, unit: string}>  $stockRows
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
            $planned = $row['planned'];
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
                    if ($sizeRetail[$size] + $price > $cfg['max'] + 0.005) {
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
     * Canonical product order for round-robin: category order from config (with alphabetical fallback
     * for unknown categories), then unit_price DESC, tiebreak id ASC.
     *
     * @param  array<int, array{product: Product, planned: float, unit: string}>  $stockRows
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
     * @param  array<int, array{product: Product, planned: float, unit: string}>  $stockRows
     * @param  list<array<string, mixed>>  $sizeBlocks
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @return list<array{product_id: int, name: string, unit: string, unit_price: float, planned: float, used_total: float, remaining: float}>
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
            $u = round((float) ($used[$pid] ?? 0), 2);
            $out[] = [
                'product_id' => (int) $pid,
                'name' => (string) $product->name,
                'unit' => (string) $row['unit'],
                'unit_price' => round((float) $product->unit_price, 2),
                'planned' => round($planned, 2),
                'used_total' => $u,
                'remaining' => round($planned - $u, 2),
                'missing_stock' => (bool) ($row['missing_stock'] ?? false),
            ];
        }
        usort($out, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Soft warning when submitted distribution exceeds planned stock for a product.
     *
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $sizes
     */
    private function logIfOverPlanned(Carbon $week, array $sizes): void
    {
        $subsBySize = $this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($week)
            ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        $totalsByProduct = [];
        foreach ($sizes as $size => $lines) {
            $n = $subsBySize->get((string) $size)?->count() ?? 0;
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
            if ($planned !== null && $needed > $planned + 0.005) {
                Log::warning('admin_box_setup.over_planned', [
                    'week_start_date' => $week->toDateString(),
                    'product_id' => $pid,
                    'planned' => $planned,
                    'needed' => round($needed, 2),
                ]);
            }
        }
    }
}
