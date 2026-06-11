<?php

namespace Tests\Feature\Shop;

use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ImageProcessorTest extends TestCase
{
    public function test_thumb_path_is_derived_with_jpg_extension(): void
    {
        $this->assertSame('products/5/thumb_abc.jpg', ImageProcessor::thumbPath('products/5/abc.png'));
        $this->assertSame('products/5/thumb_abc.jpg', ImageProcessor::thumbPath('products/5/abc.jpg'));
        $this->assertSame('thumb_abc.jpg', ImageProcessor::thumbPath('abc.webp'));
    }

    public function test_store_upload_writes_downscaled_original_and_thumbnail(): void
    {
        Storage::fake('public');
        $processor = new ImageProcessor;

        // A 2400px-wide photo, larger than both target dimensions.
        $file = UploadedFile::fake()->image('photo.jpg', 2400, 1800);

        $path = $processor->storeUpload($file, 'products/7');

        $disk = Storage::disk('public');
        $thumbPath = ImageProcessor::thumbPath($path);

        $this->assertTrue($disk->exists($path), 'Original variant should be stored.');
        $this->assertTrue($disk->exists($thumbPath), 'Thumbnail variant should be stored.');
        $this->assertStringStartsWith('products/7/', $path);
        $this->assertStringEndsWith('.jpg', $path);

        $manager = new ImageManager(new Driver);

        // Original downscaled to the 1600px width cap.
        $original = $manager->decodePath($disk->path($path));
        $this->assertSame(ImageProcessor::MAX_ORIGINAL_WIDTH, $original->width());

        // Thumbnail is an exact square cover.
        $thumb = $manager->decodePath($disk->path($thumbPath));
        $this->assertSame(ImageProcessor::THUMB_SIZE, $thumb->width());
        $this->assertSame(ImageProcessor::THUMB_SIZE, $thumb->height());
    }

    public function test_regenerate_is_noop_for_missing_file(): void
    {
        Storage::fake('public');

        $this->assertFalse((new ImageProcessor)->regenerate('products/9/does-not-exist.jpg'));
    }
}
