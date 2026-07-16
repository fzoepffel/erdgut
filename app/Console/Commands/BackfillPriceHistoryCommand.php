<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Admin\PriceHistoryRecorder;
use Illuminate\Console\Command;

/**
 * Seeds product_price_histories with each product's CURRENT prices as the
 * first data points. Run once after deploying the price-history feature;
 * running it again is a no-op (the recorder only appends on change).
 */
class BackfillPriceHistoryCommand extends Command
{
    protected $signature = 'price-history:backfill';

    protected $description = 'Seed the price history with every product\'s current prices (idempotent)';

    public function handle(PriceHistoryRecorder $recorder): int
    {
        $count = 0;
        Product::query()->with('variants', 'producers')->chunkById(100, function ($products) use ($recorder, &$count): void {
            foreach ($products as $product) {
                $recorder->record($product);
                $count++;
            }
        });

        $this->info($count.' Produkte erfasst.');

        return self::SUCCESS;
    }
}
