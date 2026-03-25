<?php

namespace App\Services\Admin;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Subscription;
use App\Support\BoxCompositionService;
use App\Support\BoxValueRecalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin bulk fills for the weekly planner: random preset preview/apply and canonical per-size fill.
 */
final class AdminBoxPlannerFillService
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner,
        private readonly BoxCompositionService $compositionService,
        private readonly BoxValueRecalculationService $valueRecalculationService,
        private readonly CanonicalBoxTemplateBuilder $canonicalTemplateBuilder,
    ) {}

    /**
     * @return array{
     *     week_start_date: string,
     *     rows: list<array{
     *         subscription: Subscription,
     *         box: Box|null,
     *         lines: list,
     *         estimated_value: float,
     *         included_budget: float,
     *         over_budget: bool,
     *         will_create_box: bool,
     *         skipped_not_editable: bool,
     *     }>,
     * }
     */
    public function previewBulkRandom(Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $rows = [];

        foreach ($this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($weekStart) as $subscription) {
            $box = Box::query()
                ->forUser($subscription->user_id)
                ->forWeek($weekStart->toDateString())
                ->first();

            $skippedNotEditable = $box !== null && $box->status !== BoxStatus::Editable;

            $lines = $this->compositionService->previewRandomLinesForSize((string) $subscription->box_size);
            $estimated = round(array_sum(array_column($lines, 'line_total')), 2);
            $included = round((float) $subscription->includedValueForBudget(), 2);

            $rows[] = [
                'subscription' => $subscription->loadMissing('user:id,email'),
                'box' => $box,
                'lines' => $lines,
                'estimated_value' => $estimated,
                'included_budget' => $included,
                'over_budget' => $estimated > $included + 0.005,
                'will_create_box' => $box === null,
                'skipped_not_editable' => $skippedNotEditable,
            ];
        }

        return [
            'week_start_date' => $weekStart->toDateString(),
            'rows' => $rows,
        ];
    }

    public function applyBulkRandom(Carbon $weekStart): int
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $filled = 0;

        foreach ($this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($weekStart) as $subscription) {
            $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $weekStart);
            if ($box->status !== BoxStatus::Editable) {
                continue;
            }
            $this->compositionService->composePresetFromScratch($box, (string) $subscription->box_size);
            $filled++;
        }

        return $filled;
    }

    /**
     * @return array{
     *     week_start_date: string,
     *     size_blocks: list<array{
     *         box_size: string,
     *         cap_used: float,
     *         template_lines: list,
     *         template_total: float,
     *         subscriptions: list<array{
     *             subscription: Subscription,
     *             box: Box|null,
     *             included_budget: float,
     *             over_budget: bool,
     *             will_create_box: bool,
     *             skipped_not_editable: bool,
     *         }>,
     *     }>,
     * }
     */
    public function previewCanonical(Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $eligible = $this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($weekStart);
        $bySize = $eligible->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        $sizeBlocks = [];

        foreach ($bySize as $size => $subs) {
            /** @var Collection<int, Subscription> $subs */
            $subs = $subs->values();
            if ($subs->isEmpty()) {
                continue;
            }

            $cap = $subs->min(fn (Subscription $s): float => (float) $s->includedValueForBudget());
            $cap = max(0.0, round((float) $cap, 2));

            $template = $this->canonicalTemplateBuilder->build((string) $size, $cap);

            $subscriptionRows = [];
            foreach ($subs as $subscription) {
                $box = Box::query()
                    ->forUser($subscription->user_id)
                    ->forWeek($weekStart->toDateString())
                    ->first();

                $skippedNotEditable = $box !== null && $box->status !== BoxStatus::Editable;
                $included = round((float) $subscription->includedValueForBudget(), 2);
                $total = $template['total'];

                $subscriptionRows[] = [
                    'subscription' => $subscription->loadMissing('user:id,email'),
                    'box' => $box,
                    'included_budget' => $included,
                    'over_budget' => $total > $included + 0.005,
                    'will_create_box' => $box === null,
                    'skipped_not_editable' => $skippedNotEditable,
                ];
            }

            $sizeBlocks[] = [
                'box_size' => (string) $size,
                'cap_used' => $cap,
                'template_lines' => $template['lines'],
                'template_total' => $template['total'],
                'subscriptions' => $subscriptionRows,
            ];
        }

        return [
            'week_start_date' => $weekStart->toDateString(),
            'size_blocks' => $sizeBlocks,
        ];
    }

    public function applyCanonical(Carbon $weekStart): int
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $preview = $this->previewCanonical($weekStart);
        $updated = 0;

        DB::transaction(function () use ($preview, $weekStart, &$updated): void {
            foreach ($preview['size_blocks'] as $block) {
                $lines = $block['template_lines'];
                if ($lines === []) {
                    continue;
                }

                foreach ($block['subscriptions'] as $subRow) {
                    if ($subRow['skipped_not_editable']) {
                        continue;
                    }

                    $subscription = $subRow['subscription'];
                    $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $weekStart);
                    if ($box->status !== BoxStatus::Editable) {
                        continue;
                    }

                    $box->items()->where('is_extra', false)->delete();

                    foreach ($lines as $line) {
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
            }
        });

        return $updated;
    }
}
