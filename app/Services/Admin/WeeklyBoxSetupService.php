<?php

namespace App\Services\Admin;

use App\Enums\BoxStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\Subscription;
use App\Support\BoxValueRecalculationService;
use App\Support\DeliveryProjector;
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
        private readonly DeliveryProjector $deliveryProjector,
    ) {}

    /**
     * Overview of the next N Mondays for the calendar.
     *
     * `total_estimated_value` is **projected weekly revenue**: every eligible
     * subscription + every paid/scheduled trial contributes its size's TTC
     * (config `box.included_value_by_size`), plus `box.eggs_dairy_add_on_eur`
     * per customer who pays for the eggs add-on. It's intentionally
     * independent of whether the box has been composed yet — the customer
     * is paying that amount either way.
     *
     * @return list<array{
     *     week_start_date: string,
     *     week: Carbon,
     *     status: string,
     *     eligible_count: int,
     *     trial_eligible_count: int,
     *     boxes_count: int,
     *     trial_boxes_count: int,
     *     total_boxes_count: int,
     *     total_eligible_count: int,
     *     eggs_addon_count: int,
     *     total_estimated_value: float,
     *     per_size: array<string, array{eligible: int, boxes: int}>,
     * }>
     */
    public function calendarOverview(?int $weekCount = null): array
    {
        $firstMonday = WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
        $weeks = $this->weeklyBoxPlanner->horizonWeekStarts($firstMonday, $weekCount);

        if ($weeks === []) {
            return [];
        }

        // ERD-344: hoist per-week queries out of the loop. With 12 weeks this
        // collapses many per-week queries (subs+boxes+trials) into 3 total.
        $activeSubs = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('user')
            ->get();

        $firstWeek = $weeks[0]->toDateString();
        $lastWeek = end($weeks)->toDateString();
        $lastSunday = end($weeks)->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        // reset() resets the internal pointer that end() left dangling.
        reset($weeks);

        $boxesByWeek = Box::query()
            ->whereBetween('week_start_date', [$firstWeek, $lastWeek])
            ->get(['id', 'user_id', 'order_id', 'estimated_value', 'week_start_date'])
            ->groupBy(fn (Box $b) => $b->week_start_date instanceof Carbon
                ? $b->week_start_date->toDateString()
                : (string) $b->week_start_date
            );

        // Trial orders that ship in any of the displayed weeks. Bucketed by
        // the Monday of `scheduled_for_date` so each row gets its trials.
        $trialOrders = Order::query()
            ->where('product_type', OrderProductType::TrialBox->value)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Scheduled->value])
            ->whereBetween('scheduled_for_date', [$firstWeek, $lastSunday])
            ->get(['id', 'user_id', 'box_size', 'preferences', 'scheduled_for_date']);

        $trialsByWeek = $trialOrders->groupBy(function (Order $o): string {
            $d = $o->scheduled_for_date instanceof Carbon
                ? $o->scheduled_for_date
                : Carbon::parse((string) $o->scheduled_for_date);

            return $d->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        });

        $sizeKeys = array_keys(config('box.included_value_by_size', []));
        $sizeTtc = [];
        foreach ($sizeKeys as $size) {
            $sizeTtc[$size] = (float) config('box.included_value_by_size.'.$size, 0);
        }
        $eggsAddOnEur = (float) config('box.eggs_dairy_add_on_eur', 0);

        $rows = [];
        foreach ($weeks as $week) {
            $weekKey = $week->toDateString();

            $eligible = $activeSubs
                ->filter(fn (Subscription $s) => ! $s->isWithinPauseWindow($week))
                ->filter(fn (Subscription $s) => $this->weeklyBoxPlanner->matchesPlanCadence($s, $week))
                ->values();

            $eligibleBySize = $eligible->groupBy(fn (Subscription $s): string => (string) $s->box_size);

            $trials = $trialsByWeek->get($weekKey, collect());
            $trialsBySize = $trials->groupBy(fn (Order $o): string => (string) $o->box_size);

            $boxes = $boxesByWeek->get($weekKey, collect());

            // Match saved boxes to *eligible* subs / trials only — orphan
            // boxes (e.g. from a sub that became inactive after the box was
            // saved, or stale test data) don't count toward the workload
            // shown to the admin. Subscription boxes are matched by user_id
            // (only the rows with `order_id IS NULL`); trial boxes by
            // order_id. This guarantees `total_boxes_count` equals the sum
            // of `per_size.<s>.boxes`, so the numbers in the overview row
            // are internally consistent.
            $subBoxUserIds = $boxes->whereNull('order_id')->pluck('user_id')->all();
            $trialBoxOrderIds = $boxes->whereNotNull('order_id')->pluck('order_id')->all();

            $subBoxesBySize = $eligible
                ->filter(fn (Subscription $s) => in_array($s->user_id, $subBoxUserIds, true))
                ->groupBy(fn (Subscription $s): string => (string) $s->box_size);
            $trialBoxesBySize = $trials
                ->filter(fn (Order $o) => in_array($o->id, $trialBoxOrderIds, true))
                ->groupBy(fn (Order $o): string => (string) $o->box_size);

            $subscriptionBoxesCount = (int) $subBoxesBySize->sum(fn ($g) => $g->count());
            $trialBoxesCount = (int) $trialBoxesBySize->sum(fn ($g) => $g->count());

            $perSize = [];
            foreach ($sizeKeys as $size) {
                $perSize[$size] = [
                    'eligible' => ($eligibleBySize->get($size)?->count() ?? 0)
                        + ($trialsBySize->get($size)?->count() ?? 0),
                    'boxes' => ($subBoxesBySize->get($size)?->count() ?? 0)
                        + ($trialBoxesBySize->get($size)?->count() ?? 0),
                ];
            }

            // Eggs add-on: count of subs + trials whose preferences carry
            // the flag. Used both for the column and for the revenue total.
            $eggsAddOnCount = $eligible->filter(fn (Subscription $s) => (bool) data_get($s->preferences, 'eggs_dairy_add_on', false))->count()
                + $trials->filter(fn (Order $o) => (bool) data_get($o->preferences, 'eggs_dairy_add_on', false))->count();

            // Projected weekly revenue: each eligible box contributes its
            // size's TTC, plus eggs add-on EUR per opted-in customer.
            $totalRevenue = 0.0;
            foreach ($sizeKeys as $size) {
                $totalRevenue += ($eligibleBySize->get($size)?->count() ?? 0) * ($sizeTtc[$size] ?? 0);
                $totalRevenue += ($trialsBySize->get($size)?->count() ?? 0) * ($sizeTtc[$size] ?? 0);
            }
            $totalRevenue += $eggsAddOnCount * $eggsAddOnEur;

            $eligibleCount = $eligible->count();
            $trialEligibleCount = $trials->count();
            $totalEligibleCount = $eligibleCount + $trialEligibleCount;
            $totalBoxesCount = $subscriptionBoxesCount + $trialBoxesCount;

            $rows[] = [
                'week_start_date' => $weekKey,
                'week' => $week,
                'status' => $this->resolveStatus($totalEligibleCount, $totalBoxesCount),
                'eligible_count' => $eligibleCount,
                'trial_eligible_count' => $trialEligibleCount,
                'total_eligible_count' => $totalEligibleCount,
                'boxes_count' => $subscriptionBoxesCount,
                'trial_boxes_count' => $trialBoxesCount,
                'total_boxes_count' => $totalBoxesCount,
                'eggs_addon_count' => $eggsAddOnCount,
                'total_estimated_value' => round($totalRevenue, 2),
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

        // ERD-NEW: Trial orders shipping in the same week count toward the
        // unified per-size box totals. They share weekly stock with
        // subscriptions, so the auto-distributor must size n correctly to
        // avoid double-counting / starving downstream allocations.
        $trialsBySize = $this->weeklyBoxPlanner
            ->eligibleTrialOrdersForWeek($week)
            ->groupBy(fn (Order $o): string => (string) $o->box_size);

        $stockRows = $this->loadStockRows($week);
        $sizeConfigs = $this->sizeConfigsFromSubs($subsBySize, $marginRate, $trialsBySize);

        $existingBlocks = $this->loadExistingSizeBlocks($week, $subsBySize, $sizeConfigs, $stockRows, $trialsBySize);
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
        ?Collection $trialsBySize = null,
    ): array {
        // Map every eligible "owner" (subscription user OR trial order) to a box size
        // so we can pick a representative box per size from either source.
        $subUserIdsBySize = [];
        foreach ($subsBySize as $size => $subs) {
            foreach ($subs as $sub) {
                $subUserIdsBySize[(int) $sub->user_id] = (string) $size;
            }
        }
        $trialOrderIdsBySize = [];
        if ($trialsBySize !== null) {
            foreach ($trialsBySize as $size => $orders) {
                foreach ($orders as $order) {
                    $trialOrderIdsBySize[(int) $order->id] = (string) $size;
                }
            }
        }
        if ($subUserIdsBySize === [] && $trialOrderIdsBySize === []) {
            return [];
        }

        $boxesQuery = Box::query()
            ->forWeek($week->toDateString())
            ->where('status', BoxStatus::Editable)
            ->where(function ($q) use ($subUserIdsBySize, $trialOrderIdsBySize) {
                if ($subUserIdsBySize !== []) {
                    $q->orWhere(function ($qq) use ($subUserIdsBySize) {
                        $qq->whereIn('user_id', array_keys($subUserIdsBySize))
                            ->whereNull('order_id');
                    });
                }
                if ($trialOrderIdsBySize !== []) {
                    $q->orWhereIn('order_id', array_keys($trialOrderIdsBySize));
                }
            })
            ->with([
                'items' => fn ($q) => $q->where('is_extra', false)
                    ->with([
                        'product:id,category,unit_price,metadata,is_available,is_published,is_egg_addon',
                        'product.translations',
                    ]),
            ])
            ->orderBy('id');

        $boxes = $boxesQuery->get();

        // Eggs add-on items live in the per-customer eggs panel, not the
        // size template. Strip them out when re-deriving the template from
        // an existing box.
        foreach ($boxes as $box) {
            $box->setRelation(
                'items',
                $box->items->reject(fn ($item) => (bool) ($item->product?->is_egg_addon ?? false))->values()
            );
        }

        if ($boxes->isEmpty()) {
            return [];
        }

        $representativeBySize = [];
        foreach ($boxes as $box) {
            // Trial box → resolve via order_id; subscription box → resolve via user_id.
            $size = $box->order_id !== null
                ? ($trialOrderIdsBySize[(int) $box->order_id] ?? null)
                : ($subUserIdsBySize[(int) $box->user_id] ?? null);
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

        $trialsBySize = $this->weeklyBoxPlanner
            ->eligibleTrialOrdersForWeek($week)
            ->groupBy(fn (Order $o): string => (string) $o->box_size);

        $stockRows = $this->loadStockRows($week);
        $sizeConfigs = $this->sizeConfigsFromSubs($subsBySize, $marginRate, $trialsBySize);

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
     * Wipe every editable box for the given week — both subscription boxes
     * (order_id IS NULL) and trial boxes (order_id linked to an eligible
     * trial order) — so the next setup pass rebuilds from a clean slate.
     * Locked and delivered boxes are intentionally untouched — admins reset
     * only what the planner still owns.
     *
     * Returns the number of boxes deleted.
     */
    public function resetWeek(Carbon $week): int
    {
        $week = $week->copy()->startOfDay();

        return DB::transaction(function () use ($week): int {
            // Trial orders for the week — only their boxes can be wiped on reset.
            $trialOrderIds = $this->weeklyBoxPlanner
                ->eligibleTrialOrdersForWeek($week)
                ->pluck('id')
                ->all();

            $boxes = Box::query()
                ->forWeek($week->toDateString())
                ->where('status', BoxStatus::Editable)
                ->where(function ($q) use ($trialOrderIds) {
                    $q->whereNull('order_id');
                    if ($trialOrderIds !== []) {
                        $q->orWhereIn('order_id', $trialOrderIds);
                    }
                })
                ->with('items')
                ->get();

            $count = 0;
            foreach ($boxes as $box) {
                $box->items()->delete();
                $box->delete();
                $count++;
            }

            return $count;
        });
    }

    /**
     * Apply the submitted distribution: create/update one Box + BoxItems per eligible subscription.
     *
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $sizes
     */
    /**
     * Pick a default eggs-add-on product for a customer's box. Returns the
     * first `is_egg_addon=true` product that has stock for the week. The
     * per-customer panel exposes a dropdown of all such products so the
     * admin can pick a different producer when multiple are stocked.
     */
    public function resolveEggsAddOnProduct(Carbon $week): ?Product
    {
        return $this->availableEggAddOnProducts($week)->first();
    }

    /**
     * Per-customer distribution apply (subscriptions + trial orders).
     *
     * Customers are keyed by a stable form key:
     *   - subscriptions: `s_<user_id>` (or just `<user_id>` for backwards compat)
     *   - trial orders:  `t_<order_id>`
     *
     * The lines payload is treated as the FINAL composition for that
     * customer's box (size template + admin overrides + eggs slot already
     * merged client-side).
     *
     * Side effects (subscriptions only — trials don't carry eggs add-on yet):
     *  - Persists `composition_admin_ok_at` + `composition_admin_ok_by` when the
     *    matching $customerOk entry is truthy.
     *  - When the customer has the eggs add-on flag but no `is_egg_addon=true`
     *    product is in the saved lines, marks `eggs_addon_unfulfilled_at`,
     *    issues a partial refund of `box.eggs_dairy_add_on_eur` against the
     *    most recent paid Payment, and sends a notification email.
     *
     * @param  array<int|string, list<array{product_id: int, quantity: float}>>  $customers
     * @param  array<int|string, mixed>  $customerOk  matching key => truthy when admin acknowledged
     */
    public function applyPerCustomerDistribution(Carbon $week, array $customers, array $customerOk = [], ?int $actorId = null): int
    {
        $week = $week->copy()->startOfDay();
        $updated = 0;
        $unfulfilledEggsBoxes = [];

        DB::transaction(function () use ($week, $customers, $customerOk, $actorId, &$updated, &$unfulfilledEggsBoxes): void {
            $eligibleByUserId = $this->weeklyBoxPlanner
                ->eligibleSubscriptionsForWeek($week)
                ->keyBy(fn (Subscription $s): int => (int) $s->user_id);

            $eligibleTrialsByOrderId = $this->weeklyBoxPlanner
                ->eligibleTrialOrdersForWeek($week)
                ->keyBy(fn (Order $o): int => (int) $o->id);

            foreach ($customers as $key => $linesPayload) {
                $key = (string) $key;
                // Trial customer? form key is `t_<order_id>`.
                if (str_starts_with($key, 't_')) {
                    $orderId = (int) substr($key, 2);
                    $trialOrder = $eligibleTrialsByOrderId->get($orderId);
                    if ($trialOrder === null) {
                        continue;
                    }
                    if ($this->applyTrialOrderLines($trialOrder, $week, (array) $linesPayload, ! empty($customerOk[$key]), $actorId)) {
                        $updated++;
                    }

                    continue;
                }

                // Subscription customer: `s_<user_id>` or legacy bare `<user_id>`.
                $userId = (int) (str_starts_with($key, 's_') ? substr($key, 2) : $key);
                $subscription = $eligibleByUserId->get($userId);
                if ($subscription === null) {
                    continue;
                }
                $okKey = "s_$userId";
                $okFlagPayload = ! empty($customerOk[$okKey]) || ! empty($customerOk[$userId]);

                $box = Box::query()
                    ->forUser($subscription->user_id)
                    ->forWeek($week->toDateString())
                    ->whereNull('order_id')
                    ->first();

                $skippedNotEditable = $box !== null && $box->status !== BoxStatus::Editable;
                if ($skippedNotEditable) {
                    continue;
                }

                $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $week);
                if ($box->status !== BoxStatus::Editable) {
                    continue;
                }

                $resolved = $this->resolveLines((array) $linesPayload);

                $box->items()->where('is_extra', false)->delete();

                $hasEggsProductInLines = false;
                foreach ($resolved as $line) {
                    /** @var Product $product */
                    $product = $line['product'];
                    if ((bool) $product->is_egg_addon) {
                        $hasEggsProductInLines = true;
                    }
                    BoxItem::create([
                        'box_id' => $box->id,
                        'product_id' => $product->id,
                        'quantity' => (float) $line['quantity'],
                        'unit' => (string) $line['unit'],
                        'is_extra' => false,
                    ]);
                }

                $this->valueRecalculationService->recalculate($box->fresh());
                $this->deliveryProjector->projectForBox($box->fresh());

                // Persist admin OK state (or clear it if not OK'd this round).
                $okFlag = $okFlagPayload;
                $box->forceFill([
                    'composition_admin_ok_at' => $okFlag ? now() : null,
                    'composition_admin_ok_by' => $okFlag ? $actorId : null,
                ])->save();

                // Detect missing eggs and queue refund + notification.
                $eggsExpected = (bool) data_get($subscription->preferences, 'eggs_dairy_add_on', false);
                if ($eggsExpected && ! $hasEggsProductInLines) {
                    if ($box->eggs_addon_unfulfilled_at === null) {
                        $box->forceFill(['eggs_addon_unfulfilled_at' => now()])->save();
                        $unfulfilledEggsBoxes[] = $box->fresh();
                    }
                } elseif ($box->eggs_addon_unfulfilled_at !== null) {
                    // Admin added eggs after a previous unfulfilled save — clear the flag.
                    $box->forceFill(['eggs_addon_unfulfilled_at' => null])->save();
                }

                $updated++;
            }
        });

        // Process refund + notify outside the DB transaction (network I/O).
        if ($unfulfilledEggsBoxes !== []) {
            app(EggsUnfulfilledHandler::class)->handle($unfulfilledEggsBoxes, $actorId);
        }

        return $updated;
    }

    /**
     * Persist the lines for a single trial Order. Returns true when the box
     * was created/updated; false when skipped (locked/delivered or empty
     * resolved lines).
     *
     * @param  list<array{product_id: int|string, quantity: int|float|string}>  $linesPayload
     */
    private function applyTrialOrderLines(Order $order, Carbon $week, array $linesPayload, bool $okFlag, ?int $actorId): bool
    {
        $box = Box::query()
            ->where('order_id', $order->id)
            ->whereDate('week_start_date', $week->toDateString())
            ->first();

        if ($box !== null && $box->status !== BoxStatus::Editable) {
            return false;
        }

        if ($box === null) {
            $box = Box::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'week_start_date' => $week->toDateString(),
                'status' => BoxStatus::Editable,
                'modification_deadline_at' => $week->copy()->subDays(2),
                'estimated_value' => 0,
                'is_test' => (bool) $order->is_test,
            ]);
            $order->forceFill(['box_id' => $box->id])->save();
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
        $this->deliveryProjector->projectForBox($box->fresh());

        $box->forceFill([
            'composition_admin_ok_at' => $okFlag ? now() : null,
            'composition_admin_ok_by' => $okFlag ? $actorId : null,
        ])->save();

        return true;
    }

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
                    ->whereNull('order_id')
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

                // Auto-project the delivery row for this box. Previously delivery
                // rows were only created by the separate `deliveries:sync-from-boxes`
                // command (run nightly via cron, or manually via the admin button),
                // so saving boxes left a same-day gap where the customer dashboard
                // showed the upcoming box but no scheduled delivery.
                // The projector is idempotent (updateOrCreate on user_id +
                // scheduled_for_date) so re-runs of "Boxen speichern" stay safe.
                $this->deliveryProjector->projectForBox($box->fresh());

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
     * @param  Collection<string, EloquentCollection<int, Order>>|null  $trialsBySize  optional trial orders for the week
     * @return array<string, array{n: int, ttc: float, target: float, max: float, n_subs: int, n_trials: int}>
     */
    private function sizeConfigsFromSubs(Collection $subsBySize, float $marginRate, ?Collection $trialsBySize = null): array
    {
        $configured = array_keys(config('box.included_value_by_size', []));
        $configs = [];
        foreach ($configured as $size) {
            $nSubs = $subsBySize->get($size)?->count() ?? 0;
            $nTrials = $trialsBySize?->get($size)?->count() ?? 0;
            $n = $nSubs + $nTrials;
            if ($n === 0) {
                continue;
            }
            $ttc = round((float) config('box.included_value_by_size.'.$size, 0), 2);
            $target = round($ttc * (1 - $marginRate), 2);
            $configs[$size] = [
                'n' => $n,
                'n_subs' => $nSubs,
                'n_trials' => $nTrials,
                'ttc' => $ttc,
                'target' => $target,
                'max' => $ttc,
            ];
        }

        return $configs;
    }

    /**
     * Weekly stock rows with remaining-aware allocation quantities.
     *
     * The `available` field is `max(0, planned - distributed)` where distributed is the sum of
     * all box items already assigned to any box in that week (subscription + trial).
     *
     * @return array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string}>
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
     * All `is_egg_addon=true` products with weekly stock for the given week.
     * Used by the per-customer eggs panel (separate from the size template).
     *
     * @return Collection<int, Product>
     */
    public function availableEggAddOnProducts(Carbon $week): Collection
    {
        $week = $week->copy()->startOfDay();

        $stockedIds = ProductWeekStock::query()
            ->whereDate('week_start_date', $week->toDateString())
            ->where('planned_quantity', '>', 0)
            ->pluck('product_id')
            ->all();

        if ($stockedIds === []) {
            return collect();
        }

        return Product::query()
            ->visibleInCatalog()
            ->eggAddOns()
            ->whereIn('id', $stockedIds)
            ->with('producer')
            ->get();
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
     * Auto-distribution core (round-robin by category then price).
     *
     * Per product: quota_{p,s} = floor(planned_p × share_s / n_s) per box (share_s = ttc_s × n_s / Σ ttc × n).
     * For each size (largest TTC first): cycle over products in canonical order (category order from
     * config('box.composition_by_size.small') + alphabetical fallback; within category unit_price DESC
     * tiebreak id ASC), adding 1 unit per candidate per pass while it fits under max and has quota.
     * Stop when target reached, or when a full pass adds no units.
     *
     * @param  array<string, array{n: int, ttc: float, target: float, max: float}>  $sizeConfigs
     * @param  array<int, array{product: Product, planned: float, available: float, distributed: float, unit: string}>  $stockRows
     * @return list<array<string, mixed>>
     */
    private function allocateProducts(array $sizeConfigs, array $stockRows): array
    {
        // Eggs add-on products live in the per-customer eggs panel, not the
        // size template auto-distribution. The stock overview still shows
        // them — only this allocator skips them.
        //
        // Also strip products that don't actually have weekly stock for this
        // week. loadExistingSizeBlocks() back-fills the stock array with
        // products it found in saved boxes (with missing_stock=true and
        // planned=0); the auto-distributor must only spend stock that exists.
        $stockRows = array_filter($stockRows, function (array $row): bool {
            if ((bool) ($row['product']->is_egg_addon ?? false)) {
                return false;
            }
            if (! empty($row['missing_stock'])) {
                return false;
            }
            if ((float) ($row['planned'] ?? 0) <= 0) {
                return false;
            }

            return true;
        });

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
            // Match the chapter-aligned taxonomy from
            // config/box.php (D6/D7/F8 in UX review notes).
            $baseOrder = ['produce', 'eggs_and_dairy', 'bread'];
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
     * Soft warning when submitted distribution exceeds planned stock for a product.
     *
     * @param  array<string, list<array{product_id: int, quantity: float}>>  $sizes
     */
    private function logIfOverPlanned(Carbon $week, array $sizes): void
    {
        $subsBySize = $this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($week)
            ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        $baselineDistributed = $this->loadDistributedQuantities($week);

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
            $already = (float) ($baselineDistributed[(int) $pid] ?? 0.0);
            $total = $already + $needed;
            if ($planned !== null && $total > $planned + 0.005) {
                Log::warning('admin_box_setup.over_planned', [
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
