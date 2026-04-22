<?php

namespace App\Console\Commands;

use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductWeekStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogPurge extends Command
{
    protected $signature = 'catalog:purge
                            {--only-test : Only delete is_test=true products/producers}
                            {--all : Delete all products/producers (including non-test)}
                            {--dry-run : Print counts only; do not delete anything}
                            {--force : Required to perform deletions}';

    protected $description = 'Purge catalog data (products + producers) with optional cascade into weekly stock and box items.';

    public function handle(): int
    {
        $onlyTest = (bool) $this->option('only-test');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($onlyTest && $all) {
            $this->error('Use either --only-test or --all, not both.');

            return self::FAILURE;
        }

        if (! $onlyTest && ! $all) {
            $this->error('Select what to purge: pass either --only-test or --all.');

            return self::FAILURE;
        }

        $productQuery = Product::query();
        $producerQuery = Producer::query();
        if ($onlyTest) {
            $productQuery->where('is_test', true);
            $producerQuery->where('is_test', true);
        }

        $productIds = $productQuery->pluck('id');
        $producerIds = $producerQuery->pluck('id');

        $counts = [
            'box_items' => $productIds->isEmpty() ? 0 : BoxItem::query()->whereIn('product_id', $productIds)->count(),
            'product_week_stocks' => $productIds->isEmpty() ? 0 : ProductWeekStock::query()->whereIn('product_id', $productIds)->count(),
            'products' => $productIds->count(),
            'producers' => $producerIds->count(),
        ];

        $mode = $onlyTest ? 'ONLY_TEST' : 'ALL';
        $this->info("Catalog purge mode: {$mode}");
        $this->info('Would delete:');
        foreach ($counts as $k => $v) {
            $this->line("  - {$k}: {$v}");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No changes were made.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->error('Refusing to delete without --force.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($productIds, $producerIds): void {
            if ($productIds->isNotEmpty()) {
                BoxItem::query()->whereIn('product_id', $productIds)->delete();
                ProductWeekStock::query()->whereIn('product_id', $productIds)->delete();
                Product::query()->whereIn('id', $productIds)->delete();
            }

            if ($producerIds->isNotEmpty()) {
                Producer::query()->whereIn('id', $producerIds)->delete();
            }
        });

        $this->info('Catalog purge complete.');

        return self::SUCCESS;
    }
}
