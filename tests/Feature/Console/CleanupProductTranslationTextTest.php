<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupProductTranslationTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_rows_without_writing(): void
    {
        $product = Product::factory()->create();
        ProductTranslation::query()->where('product_id', $product->id)->where('locale', 'fr')->update([
            'taste_profile' => 'note d\\u0027asperge',
        ]);

        $this->artisan('product-translations:cleanup-text', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('product_id='.$product->id)
            ->expectsOutputToContain('Would update');

        $this->assertSame(
            'note d\\u0027asperge',
            ProductTranslation::query()->where('product_id', $product->id)->where('locale', 'fr')->value('taste_profile'),
        );
    }

    public function test_force_updates_corrupted_fields(): void
    {
        $product = Product::factory()->create();
        ProductTranslation::query()->where('product_id', $product->id)->where('locale', 'fr')->update([
            'taste_profile' => 'note d\\u0027asperge',
            'short_description' => 'this week&#039;s harvest',
        ]);

        $this->artisan('product-translations:cleanup-text', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Updated');

        $translation = ProductTranslation::query()
            ->where('product_id', $product->id)
            ->where('locale', 'fr')
            ->first();

        $this->assertSame("note d'asperge", $translation->taste_profile);
        $this->assertSame("this week's harvest", $translation->short_description);
    }

    public function test_refuses_to_write_without_force_or_dry_run(): void
    {
        $this->artisan('product-translations:cleanup-text')
            ->assertFailed();
    }
}
