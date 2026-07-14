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

    public function test_product_store_requires_german_but_not_en_fr(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        // German missing → rejected.
        $this->actingAs($admin)
            ->from(route('app.admin.products.create'))
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'translations' => [
                    'en' => ['name' => 'EN', 'origin' => 'EN', 'variety' => 'EN', 'taste_profile' => 'EN', 'short_description' => 'EN'],
                ],
            ])
            ->assertRedirect(route('app.admin.products.create'))
            ->assertSessionHasErrors(['translations.de.name']);

        // Only German → accepted; EN/FR rows are not stored and the
        // storefront falls back to the German texts.
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'translations' => [
                    'de' => ['name' => 'Nur Deutsch', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'Fein.', 'short_description' => 'Kurz'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $product->translations()->count());
        app()->setLocale('en');
        $this->assertSame('Nur Deutsch', $product->fresh()->name); // row-level fallback
        app()->setLocale('de');
    }

    public function test_product_update_clears_an_emptied_en_translation(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);
        $this->assertNotNull($product->translations()->where('locale', 'en')->first());

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'translations' => [
                    'de' => ['name' => 'Nur Deutsch', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'Fein.', 'short_description' => 'Kurz'],
                    'en' => ['name' => '', 'origin' => '', 'variety' => '', 'taste_profile' => '', 'short_description' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->translations()->where('locale', 'en')->first());
    }
}
