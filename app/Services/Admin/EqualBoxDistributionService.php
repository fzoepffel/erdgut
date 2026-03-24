<?php

namespace App\Services\Admin;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\BoxValueRecalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic round-robin distribution of catalog products into editable boxes for a week (ERD-191).
 *
 * @see docs/ADMIN-EQUAL-DISTRIBUTION.md
 */
final class EqualBoxDistributionService
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner,
        private readonly BoxValueRecalculationService $valueRecalculationService
    ) {}

    /**
     * @return array{
     *     week_start_date: string,
     *     skipped_locked_count: int,
     *     target_count: int,
     *     rows: list<array{
     *         box: Box,
     *         subscription: Subscription,
     *         category_counts: array<string, int>,
     *         product_names: list<string>,
     *     }>,
     * }
     */
    public function buildPreview(Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $context = $this->resolveTargetsAndAssignments($weekStart);

        return [
            'week_start_date' => $weekStart->toDateString(),
            'skipped_locked_count' => $context['skipped_locked_count'],
            'target_count' => count($context['targets']),
            'rows' => $this->rowsFromAssignments($context['targets'], $context['assignments_by_box_id']),
        ];
    }

    /**
     * @return array{boxes_updated: int, skipped_locked_count: int}
     */
    public function apply(Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $context = $this->resolveTargetsAndAssignments($weekStart);

        $updated = 0;

        DB::transaction(function () use ($context, &$updated): void {
            foreach ($context['targets'] as $target) {
                $box = $target['box'];
                $box->items()->where('is_extra', false)->delete();

                $lines = $context['assignments_by_box_id'][$box->id] ?? [];
                foreach ($lines as $line) {
                    $product = $line['product'];
                    BoxItem::create([
                        'box_id' => $box->id,
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit' => data_get($product->metadata, 'unit', 'piece'),
                        'is_extra' => false,
                    ]);
                }

                $this->valueRecalculationService->recalculate($box->fresh());
                $updated++;
            }
        });

        return [
            'boxes_updated' => $updated,
            'skipped_locked_count' => $context['skipped_locked_count'],
        ];
    }

    /**
     * @return array{
     *     targets: list<array{box: Box, subscription: Subscription}>,
     *     skipped_locked_count: int,
     *     assignments_by_box_id: array<int, list<array{product: Product, category: string}>>,
     * }
     */
    private function resolveTargetsAndAssignments(Carbon $weekStart): array
    {
        $skippedLockedCount = 0;
        $targets = [];

        foreach ($this->weeklyBoxPlanner->eligibleSubscriptionsForWeek($weekStart) as $subscription) {
            $box = $this->weeklyBoxPlanner->ensureEmptyBox($subscription, $weekStart);
            if ($box->status !== BoxStatus::Editable) {
                $skippedLockedCount++;

                continue;
            }
            $targets[] = [
                'box' => $box->loadMissing('user:id,email'),
                'subscription' => $subscription,
            ];
        }

        usort($targets, static fn (array $a, array $b): int => $a['box']->id <=> $b['box']->id);

        $categories = $this->unionCategoriesForTargets($targets);
        $assignmentsByBoxId = [];

        foreach ($targets as $target) {
            $assignmentsByBoxId[$target['box']->id] = [];
        }

        foreach ($categories as $category) {
            $slots = [];
            foreach ($targets as $target) {
                $boxSize = (string) $target['subscription']->box_size;
                $rules = (array) config("box.composition_by_size.{$boxSize}", []);
                $k = (int) ($rules[$category] ?? 0);
                for ($i = 0; $i < $k; $i++) {
                    $slots[] = $target['box'];
                }
            }

            $pool = Product::query()
                ->visibleInCatalog()
                ->where('category', $category)
                ->orderBy('id')
                ->get();

            if ($pool->isEmpty()) {
                continue;
            }

            $poolCount = $pool->count();
            foreach ($slots as $i => $box) {
                /** @var Product $product */
                $product = $pool[$i % $poolCount];
                $assignmentsByBoxId[$box->id][] = [
                    'product' => $product,
                    'category' => $category,
                ];
            }
        }

        return [
            'targets' => $targets,
            'skipped_locked_count' => $skippedLockedCount,
            'assignments_by_box_id' => $assignmentsByBoxId,
        ];
    }

    /**
     * @param  list<array{box: Box, subscription: Subscription}>  $targets
     * @param  array<int, list<array{product: Product, category: string}>>  $assignmentsByBoxId
     * @return list<array{box: Box, subscription: Subscription, category_counts: array<string, int>, product_names: list<string>}>
     */
    private function rowsFromAssignments(array $targets, array $assignmentsByBoxId): array
    {
        $rows = [];

        foreach ($targets as $target) {
            $box = $target['box'];
            $lines = $assignmentsByBoxId[$box->id] ?? [];
            $categoryCounts = [];
            $productNames = [];

            foreach ($lines as $line) {
                $cat = $line['category'];
                $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
                $productNames[] = $line['product']->name;
            }

            $rows[] = [
                'box' => $box,
                'subscription' => $target['subscription'],
                'category_counts' => $categoryCounts,
                'product_names' => $productNames,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{box: Box, subscription: Subscription}>  $targets
     * @return list<string>
     */
    private function unionCategoriesForTargets(array $targets): array
    {
        $keys = [];
        foreach ($targets as $target) {
            $boxSize = (string) $target['subscription']->box_size;
            $rules = (array) config("box.composition_by_size.{$boxSize}", []);
            foreach (array_keys($rules) as $key) {
                $keys[$key] = true;
            }
        }

        $categories = array_keys($keys);
        sort($categories);

        return $categories;
    }
}
