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
     * Build the initial auto-distribution payload for the setup dialog.
     *
     * @return array{
     *     week_start_date: string,
     *     margin_rate: float,
     *     size_blocks: list<array{
     *         box_size: string,
     *         n: int,
     *         ttc: float,
     *         target: float,
     *         max: float,
     *         retail_sum: float,
     *         over_target: bool,
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
        $sizeBlocks = $this->allocateProducts($sizeConfigs, $stockRows);

        return [
            'week_start_date' => $week->toDateString(),
            'margin_rate' => $marginRate,
            'size_blocks' => $sizeBlocks,
            'stock' => $this->buildStockSummary($stockRows, $sizeBlocks, $sizeConfigs),
        ];
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
     * Auto-distribution core.
     *
     * Per product: quota_{p,s} = floor(planned_p × share_s / n_s) per box (share_s = ttc_s × n_s / Σ ttc × n).
     * Per size (largest TTC first): greedy pick (expensive first) up to max, stop when target reached.
     * Remainder pass (cheapest first) tops up whatever is left under max.
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
        $remainderPool = [];
        foreach ($stockRows as $pid => $row) {
            $planned = $row['planned'];
            $allocatedTotal = 0.0;
            foreach ($sizeConfigs as $size => $cfg) {
                $share = $weights[$size] / $weightSum;
                $perBox = (int) floor($planned * $share / max($cfg['n'], 1));
                $perBoxQuota[$pid][$size] = max(0, $perBox);
                $allocatedTotal += $perBoxQuota[$pid][$size] * $cfg['n'];
            }
            $remainderPool[$pid] = max(0.0, $planned - $allocatedTotal);
        }

        $sizeLines = [];
        $sizeRetail = [];
        foreach ($sizeOrder as $size) {
            $sizeLines[$size] = [];
            $sizeRetail[$size] = 0.0;
        }

        $candidatesExpensiveFirst = array_values($stockRows);
        usort($candidatesExpensiveFirst, function (array $a, array $b) {
            $cmp = (float) $b['product']->unit_price <=> (float) $a['product']->unit_price;
            if ($cmp !== 0) {
                return $cmp;
            }

            return (int) $a['product']->id <=> (int) $b['product']->id;
        });

        foreach ($sizeOrder as $size) {
            $cfg = $sizeConfigs[$size];
            foreach ($candidatesExpensiveFirst as $row) {
                if ($sizeRetail[$size] >= $cfg['target']) {
                    break;
                }
                $pid = (int) $row['product']->id;
                $quota = $perBoxQuota[$pid][$size] ?? 0;
                if ($quota <= 0) {
                    continue;
                }
                $price = (float) $row['product']->unit_price;
                if ($price <= 0) {
                    continue;
                }
                $maxUnitsFitting = (int) floor(($cfg['max'] - $sizeRetail[$size] + 0.005) / $price);
                $unitsToAdd = min($quota, $maxUnitsFitting);
                if ($unitsToAdd <= 0) {
                    continue;
                }

                $sizeLines[$size][$pid] = ($sizeLines[$size][$pid] ?? 0) + $unitsToAdd;
                $sizeRetail[$size] += $unitsToAdd * $price;
                $perBoxQuota[$pid][$size] -= $unitsToAdd;
            }
        }

        $candidatesCheapestFirst = array_values($stockRows);
        usort($candidatesCheapestFirst, function (array $a, array $b) {
            $cmp = (float) $a['product']->unit_price <=> (float) $b['product']->unit_price;
            if ($cmp !== 0) {
                return $cmp;
            }

            return (int) $a['product']->id <=> (int) $b['product']->id;
        });

        foreach ($sizeOrder as $size) {
            $cfg = $sizeConfigs[$size];
            if ($sizeRetail[$size] >= $cfg['target']) {
                continue;
            }
            foreach ($candidatesCheapestFirst as $row) {
                if ($sizeRetail[$size] >= $cfg['target']) {
                    break;
                }
                $pid = (int) $row['product']->id;
                $price = (float) $row['product']->unit_price;
                if ($price <= 0) {
                    continue;
                }
                $availableRemainder = (int) floor(($remainderPool[$pid] ?? 0) / max($cfg['n'], 1));
                if ($availableRemainder <= 0) {
                    continue;
                }
                $maxUnitsFitting = (int) floor(($cfg['max'] - $sizeRetail[$size] + 0.005) / $price);
                $unitsToAdd = min($availableRemainder, $maxUnitsFitting);
                if ($unitsToAdd <= 0) {
                    continue;
                }

                $sizeLines[$size][$pid] = ($sizeLines[$size][$pid] ?? 0) + $unitsToAdd;
                $sizeRetail[$size] += $unitsToAdd * $price;
                $remainderPool[$pid] = max(0.0, ($remainderPool[$pid] ?? 0) - $unitsToAdd * $cfg['n']);
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
