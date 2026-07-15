<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationRequiredValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_store_requires_all_three_locales(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.producers.create'))
            ->post(route('app.admin.producers.store'), [
                'translations' => [
                    'de' => ['name' => 'DE', 'region' => 'DE', 'description' => 'DE'],
                    'en' => ['name' => 'EN', 'region' => 'EN', 'description' => 'EN'],
                    // fr missing
                ],
            ])
            ->assertRedirect(route('app.admin.producers.create'))
            ->assertSessionHasErrors(['translations.fr.name', 'translations.fr.region', 'translations.fr.description']);
    }

    public function test_product_store_requires_all_three_locales(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.create'))
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'translations' => [
                    'de' => ['name' => 'DE', 'origin' => 'DE', 'variety' => 'DE', 'taste_profile' => 'DE'],
                    'en' => ['name' => 'EN', 'origin' => 'EN', 'variety' => 'EN', 'taste_profile' => 'EN'],
                    // fr missing
                ],
            ])
            ->assertRedirect(route('app.admin.products.create'))
            ->assertSessionHasErrors([
                'translations.fr.name',
                'translations.fr.origin',
                'translations.fr.variety',
                'translations.fr.taste_profile',
                'translations.fr.short_description',
            ]);
    }

    public function test_display_falls_back_to_german_for_legacy_rows_without_a_locale(): void
    {
        // The FORM requires all three locales, but the DISPLAY layer stays
        // tolerant: legacy products missing an EN/FR row render the German
        // texts instead of breaking.
        Producer::factory()->create();
        $product = Product::factory()->create();
        $product->translations()->where('locale', 'de')->update(['name' => 'Nur Deutsch']);
        $product->translations()->whereIn('locale', ['en', 'fr'])->delete();

        app()->setLocale('en');
        $this->assertSame('Nur Deutsch', $product->fresh()->name);
        app()->setLocale('de');
    }
}
