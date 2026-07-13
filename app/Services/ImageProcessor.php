<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Resizes and stores catalog images on the public disk.
 *
 * Every upload produces two JPEG variants written side by side:
 *  - a downscaled "original" (capped at {@see MAX_ORIGINAL_WIDTH}px wide) for
 *    detail pages — phone photos straight off a camera are multi-megabyte and
 *    were previously served verbatim;
 *  - a square card "thumbnail" ({@see THUMB_SIZE}px) named with the
 *    {@see THUMB_PREFIX} prefix so it's derivable from the original path.
 *
 * Pure-PHP GD driver — no Imagick / Node / Chrome — so it runs on IONOS shared
 * hosting. All output is JPEG regardless of input format, keeping derived
 * filenames predictable.
 */
class ImageProcessor
{
    /** Max width (px) for the stored "original"; never upscales smaller images. */
    public const MAX_ORIGINAL_WIDTH = 1600;

    /** Square card thumbnail edge length (px). */
    public const THUMB_SIZE = 600;

    /** Filename prefix marking a derived thumbnail. */
    public const THUMB_PREFIX = 'thumb_';

    private const ORIGINAL_QUALITY = 82;

    private const THUMB_QUALITY = 80;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Process an uploaded file into $directory on the public disk and return
     * the stored (downscaled) original's path. A thumbnail is written next to
     * it. Replaces a raw `Storage::putFile()` call.
     */
    public function storeUpload(UploadedFile $file, string $directory): string
    {
        $path = trim($directory, '/').'/'.Str::random(40).'.jpg';

        $this->writeVariants($file->getRealPath(), $path);

        return $path;
    }

    /**
     * Remove a stored public image together with its derived thumbnail.
     * Shared by the admin product controller and the merge service.
     */
    public static function deletePublicImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk('public');

        foreach ([$path, self::thumbPath($path)] as $target) {
            if ($disk->exists($target)) {
                $disk->delete($target);
            }
        }
    }

    /**
     * Thumbnail path derived from an original image path:
     * `products/5/abc.png` → `products/5/thumb_abc.jpg`.
     */
    public static function thumbPath(string $originalPath): string
    {
        $dir = trim(dirname($originalPath), '/.');
        $name = pathinfo($originalPath, PATHINFO_FILENAME);
        $thumb = self::THUMB_PREFIX.$name.'.jpg';

        return $dir === '' ? $thumb : $dir.'/'.$thumb;
    }

    /**
     * (Re)generate both variants for an image already stored on the public
     * disk. Idempotent — used by the `images:reprocess` backfill command.
     * No-op (returns false) when the stored file is missing.
     */
    public function regenerate(string $storedPath): bool
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($storedPath)) {
            return false;
        }

        $this->writeVariants($disk->path($storedPath), $storedPath);

        return true;
    }

    /**
     * Read $source once per variant, resize, encode JPEG, and write both the
     * downscaled original (to $targetPath) and the thumbnail (alongside it).
     */
    private function writeVariants(string $source, string $targetPath): void
    {
        $disk = Storage::disk('public');

        $original = $this->manager->decodePath($source)
            ->scaleDown(width: self::MAX_ORIGINAL_WIDTH)
            ->encode(new JpegEncoder(quality: self::ORIGINAL_QUALITY));
        $disk->put($targetPath, (string) $original);

        $thumb = $this->manager->decodePath($source)
            ->cover(self::THUMB_SIZE, self::THUMB_SIZE)
            ->encode(new JpegEncoder(quality: self::THUMB_QUALITY));
        $disk->put(self::thumbPath($targetPath), (string) $thumb);
    }
}
