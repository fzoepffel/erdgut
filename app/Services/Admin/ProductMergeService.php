<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageProcessor;
use App\Support\AuditLogger;
use App\Support\InventoryCache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Merges a duplicate product (the LOSER) into the product that stays
 * (the WINNER). Fred's rule: the winner wins — the loser's stock
 * inflows, inflow schedules, order lines (shop, restaurant, standing),
 * box lines, pairings and engagement move over; its texts, images and
 * price rules are dropped; its grades move only when the winner lacks
 * a same-named one (otherwise references remap to the winner's grade);
 * farms the winner lacks are appended to its supplier list (the
 * winner's primary stays primary); the loser is deleted.
 *
 * Notes:
 *  - box_items carry per-row unit/price snapshots and have no unique
 *    constraint, so they are remapped verbatim (a box holding both
 *    products keeps two lines).
 *  - Checkout drafts in visitor sessions may reference the loser for
 *    up to 24h; checkout validation rejects unknown products, so no
 *    handling is needed here.
 */
class ProductMergeService
{
    /** Tables carrying a variant_id that must follow a grade remap. */
    private const VARIANT_REF_TABLES = [
        'shop_order_items',
        'restaurant_order_items',
        'restaurant_standing_order_items',
        'product_stock_inflows',
        'product_stock_inflow_schedules',
    ];

    /**
     * Read-only summary for the confirmation page and the audit trail.
     *
     * @return array<string, mixed>
     */
    public function counts(Product $winner, Product $loser): array
    {
        $variantSplit = $this->variantSplit($winner, $loser);

        $currentFarmIds = $this->producerIds($winner);
        $appendedFarms = $loser->producers()->get()
            ->reject(fn ($p): bool => in_array($p->id, $currentFarmIds, true))
            ->map(fn ($p): string => (string) $p->name)
            ->values()
            ->all();

        return [
            'inflows' => DB::table('product_stock_inflows')->where('product_id', $loser->id)->count(),
            'schedules' => DB::table('product_stock_inflow_schedules')->where('product_id', $loser->id)->count(),
            'shop_lines' => DB::table('shop_order_items')->where('product_id', $loser->id)->count(),
            'restaurant_lines' => DB::table('restaurant_order_items')->where('product_id', $loser->id)->count(),
            'standing_lines' => DB::table('restaurant_standing_order_items')->where('product_id', $loser->id)->count(),
            'box_items' => DB::table('box_items')->where('product_id', $loser->id)->count(),
            'engagement_days' => DB::table('product_engagement_daily')->where('product_id', $loser->id)->count(),
            'pairings' => DB::table('product_pairings')
                ->where('product_id', $loser->id)
                ->where('paired_product_id', '!=', $winner->id)
                ->count(),
            'variants_moved' => array_values(array_map(
                fn (ProductVariant $v): string => $v->localizedName(),
                $variantSplit['move'],
            )),
            'variants_remapped' => array_values(array_map(
                fn (array $pair): string => $pair[0]->localizedName(),
                $variantSplit['remap'],
            )),
            'producers_appended' => $appendedFarms,
            'dropped_images' => (int) DB::table('product_images')->where('product_id', $loser->id)->count()
                + (($loser->image_path ?? '') !== '' ? 1 : 0),
            'dropped_price_rules' => DB::table('product_price_rules')->where('product_id', $loser->id)->count(),
        ];
    }

    /**
     * Ids of products that must never be merged away because runtime
     * configuration points at them.
     *
     * @return list<int>
     */
    public static function protectedProductIds(): array
    {
        return array_values(array_filter(array_map(
            fn ($v): int => (int) $v,
            [
                config('spargel.white_product_id'),
                config('spargel.green_product_id'),
                config('box.eggs_dairy_add_on_product_id'),
            ],
        )));
    }

    public function merge(Product $winner, Product $loser, ?int $actorUserId = null): void
    {
        if ($winner->id === $loser->id) {
            throw new RuntimeException(__('admin.products.merge.error_self'));
        }
        if (in_array($loser->id, self::protectedProductIds(), true)) {
            throw new RuntimeException(__('admin.products.merge.error_protected'));
        }

        $counts = $this->counts($winner, $loser);
        $loserName = (string) $loser->name;
        $imagePaths = array_values(array_filter([
            $loser->image_path,
            ...$loser->images()->pluck('path')->all(),
        ]));

        DB::transaction(function () use ($winner, $loser, $counts, $loserName, $actorUserId): void {
            // The loser's pricing is dropped (the winner's rules win).
            // price_rule_id on order items is nullOnDelete, so pointers
            // null out at the DB level; line prices are snapshots.
            DB::table('product_price_rules')->where('product_id', $loser->id)->delete();

            // Grades: same-named ones remap their references onto the
            // winner's grade; the rest move over keeping their id (so
            // nothing referencing them needs rewriting).
            $split = $this->variantSplit($winner, $loser);
            foreach ($split['remap'] as [$loserVariant, $winnerVariant]) {
                foreach (self::VARIANT_REF_TABLES as $table) {
                    DB::table($table)
                        ->where('variant_id', $loserVariant->id)
                        ->update(['variant_id' => $winnerVariant->id]);
                }
                DB::table('product_variants')->where('id', $loserVariant->id)->delete();
            }
            $nextPosition = 1 + (int) DB::table('product_variants')
                ->where('product_id', $winner->id)
                ->max('position');
            foreach ($split['move'] as $loserVariant) {
                DB::table('product_variants')
                    ->where('id', $loserVariant->id)
                    ->update(['product_id' => $winner->id, 'position' => $nextPosition++]);
            }

            // Inflows recorded without a farm would silently re-attribute
            // to the WINNER's primary after the move — stamp them with the
            // loser's primary first (no primary: leave null).
            $loserPrimary = $loser->producers()->first()?->id ?? $loser->producer_id;
            if ($loserPrimary !== null) {
                foreach (['product_stock_inflows', 'product_stock_inflow_schedules'] as $table) {
                    DB::table($table)
                        ->where('product_id', $loser->id)
                        ->whereNull('producer_id')
                        ->update(['producer_id' => $loserPrimary]);
                }
            }

            // Move inventory and every order line. shop_order_items may
            // have lost its FK in a later nullable-change migration, so
            // the remap (not RESTRICT) is what guarantees integrity here.
            foreach ([
                'product_stock_inflows',
                'product_stock_inflow_schedules',
                'shop_order_items',
                'restaurant_order_items',
                'restaurant_standing_order_items',
                'box_items',
            ] as $table) {
                DB::table($table)
                    ->where('product_id', $loser->id)
                    ->update(['product_id' => $winner->id]);
            }

            // Pairings: transfer both directions, dedupe via the unique
            // pair index; the winner–loser pairing itself disappears.
            $now = now();
            foreach (DB::table('product_pairings')->where('product_id', $loser->id)->get() as $row) {
                if ((int) $row->paired_product_id !== $winner->id) {
                    DB::table('product_pairings')->insertOrIgnore([
                        'product_id' => $winner->id,
                        'paired_product_id' => $row->paired_product_id,
                        'note' => $row->note,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            foreach (DB::table('product_pairings')->where('paired_product_id', $loser->id)->get() as $row) {
                if ((int) $row->product_id !== $winner->id) {
                    DB::table('product_pairings')->insertOrIgnore([
                        'product_id' => $row->product_id,
                        'paired_product_id' => $winner->id,
                        'note' => $row->note,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            DB::table('product_pairings')
                ->where('product_id', $loser->id)
                ->orWhere('paired_product_id', $loser->id)
                ->delete();

            // Engagement: unique per (product, day) — colliding days sum
            // both counters, the rest simply move.
            foreach (DB::table('product_engagement_daily')->where('product_id', $loser->id)->get() as $row) {
                $updated = DB::table('product_engagement_daily')
                    ->where('product_id', $winner->id)
                    ->where('day', $row->day)
                    ->update([
                        'impressions' => DB::raw('impressions + '.(int) $row->impressions),
                        'add_to_carts' => DB::raw('add_to_carts + '.(int) $row->add_to_carts),
                    ]);
                if ($updated > 0) {
                    DB::table('product_engagement_daily')->where('id', $row->id)->delete();
                } else {
                    DB::table('product_engagement_daily')->where('id', $row->id)->update(['product_id' => $winner->id]);
                }
            }

            // Farms the winner lacks get appended (loser's order); the
            // winner's primary stays at position 0.
            $currentFarmIds = $this->producerIds($winner);
            $merged = array_values(array_unique(array_merge($currentFarmIds, $this->producerIds($loser))));
            if ($merged !== [] && $merged !== $currentFarmIds) {
                $winner->syncProducers($merged);
            }

            AuditLogger::log('product', $winner->id, 'merge_absorbed', null, null, null, [
                'merged_product_id' => $loser->id,
                'merged_product_name' => $loserName,
                'counts' => $counts,
            ], $actorUserId);
            AuditLogger::log('product', $loser->id, 'merge_deleted', null, null, null, [
                'merged_into_product_id' => $winner->id,
            ], $actorUserId);

            // Everything referencing the loser is remapped; the delete
            // cascades translations, image rows and the producer pivot.
            $loser->delete();
        });

        // Files only after a successful commit, so a rollback never
        // leaves DB rows pointing at deleted images.
        foreach ($imagePaths as $path) {
            ImageProcessor::deletePublicImage($path);
        }

        InventoryCache::bump();
    }

    /**
     * Split the loser's grades into ones that MOVE (winner lacks the
     * name) and ones that REMAP onto the winner's same-named grade.
     * Matching: trimmed, case-insensitive de-locale label (fallback
     * chain inside localizedName()).
     *
     * @return array{move: list<ProductVariant>, remap: list<array{0: ProductVariant, 1: ProductVariant}>}
     */
    private function variantSplit(Product $winner, Product $loser): array
    {
        $winnerByName = [];
        foreach ($winner->variants()->get() as $variant) {
            $winnerByName[mb_strtolower($variant->localizedName('de'))] = $variant;
        }

        $move = [];
        $remap = [];
        foreach ($loser->variants()->orderBy('position')->get() as $variant) {
            $key = mb_strtolower($variant->localizedName('de'));
            if (isset($winnerByName[$key])) {
                $remap[] = [$variant, $winnerByName[$key]];
            } else {
                $move[] = $variant;
            }
        }

        return ['move' => $move, 'remap' => $remap];
    }

    /**
     * Registered farm ids in pivot order, falling back to the legacy
     * single column for pre-backfill rows.
     *
     * @return list<int>
     */
    private function producerIds(Product $product): array
    {
        $ids = $product->producers()->pluck('producers.id')->map(fn ($v): int => (int) $v)->all();
        if ($ids === [] && $product->producer_id !== null) {
            $ids = [(int) $product->producer_id];
        }

        return $ids;
    }
}
