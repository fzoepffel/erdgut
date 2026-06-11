<?php

namespace App\Console\Commands;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfills the downscaled-original + thumbnail variants for catalog images
 * uploaded before the {@see ImageProcessor} pipeline existed.
 *
 * Idempotent: by default it skips an image that already has a thumbnail; pass
 * `--force` to regenerate everything (e.g. after changing target dimensions).
 */
class ReprocessImages extends Command
{
    protected $signature = 'images:reprocess {--force : Regenerate even when a thumbnail already exists}';

    protected $description = 'Downscale catalog originals and (re)generate thumbnails for products, gallery images, and producers';

    public function handle(ImageProcessor $processor): int
    {
        $force = (bool) $this->option('force');
        $disk = Storage::disk('public');
        $processed = 0;
        $skipped = 0;

        $run = function (?string $path) use ($processor, $disk, $force, &$processed, &$skipped): void {
            if ($path === null || $path === '') {
                return;
            }

            if (! $force && $disk->exists(ImageProcessor::thumbPath($path))) {
                $skipped++;

                return;
            }

            if ($processor->regenerate($path)) {
                $processed++;
                $this->line("  ✓ {$path}");
            } else {
                $this->warn("  ! missing on disk: {$path}");
            }
        };

        $this->info('Products (primary image)…');
        Product::query()->whereNotNull('image_path')->chunkById(100, function ($products) use ($run): void {
            foreach ($products as $product) {
                $run($product->image_path);
            }
        });

        $this->info('Product gallery images…');
        ProductImage::query()->whereNotNull('path')->chunkById(100, function ($images) use ($run): void {
            foreach ($images as $image) {
                $run($image->path);
            }
        });

        $this->info('Producers (primary image)…');
        Producer::query()->whereNotNull('image_path')->chunkById(100, function ($producers) use ($run): void {
            foreach ($producers as $producer) {
                $run($producer->image_path);
            }
        });

        $this->newLine();
        $this->info("Done. Regenerated {$processed}, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
