<?php

namespace App\Services\Admin;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\BoxValueRecalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin bulk fills for the weekly planner: canonical per-size fill (Wochenmenü).
 */
final class AdminBoxPlannerFillService
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner,
        private readonly BoxValueRecalculationService $valueRecalculationService,
        private readonly CanonicalBoxTemplateBuilder $canonicalTemplateBuilder,
    ) {}

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

    /**
     * @param  array<string, array{lines: list<array{product_id: int, quantity: float}>}>  $templatesBySize
     */
    public function applyCanonicalFromRequest(Carbon $weekStart, array $templatesBySize): int
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $updated = 0;

        DB::transaction(function () use ($weekStart, $templatesBySize, &$updated): void {
            foreach ($this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($weekStart) as $subscription) {
                $size = (string) $subscription->box_size;
                if (! isset($templatesBySize[$size]['lines']) || ! is_array($templatesBySize[$size]['lines'])) {
                    continue;
                }

                $linesPayload = $templatesBySize[$size]['lines'];

                $box = Box::query()
                    ->forUser($subscription->user_id)
                    ->forWeek($weekStart->toDateString())
                    ->first();

                $skippedNotEditable = $box !== null && $box->status !== BoxStatus::Editable;
                if ($skippedNotEditable) {
                    continue;
                }

                $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $weekStart);
                if ($box->status !== BoxStatus::Editable) {
                    continue;
                }

                $resolved = $this->resolveTemplateLines($linesPayload);
                if ($resolved === []) {
                    $box->items()->where('is_extra', false)->delete();
                    $this->valueRecalculationService->recalculate($box->fresh());
                    $updated++;

                    continue;
                }

                $box->items()->where('is_extra', false)->delete();

                foreach ($resolved as $line) {
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
    private function resolveTemplateLines(array $linesPayload): array
    {
        $out = [];
        foreach ($linesPayload as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);
            if ($productId < 1 || $qty < 0.01) {
                continue;
            }
            $product = Product::query()->visibleInCatalog()->find($productId);
            if ($product === null) {
                continue;
            }
            $out[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit' => (string) data_get($product->metadata, 'unit', 'piece'),
            ];
        }

        return $out;
    }
}
