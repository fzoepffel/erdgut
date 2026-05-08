<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
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
                'unit_price' => '1.00',
                'translations' => [
                    'de' => ['name' => 'DE', 'unit' => 'kg', 'origin' => 'DE', 'variety' => 'DE', 'taste_profile' => 'DE'],
                    'en' => ['name' => 'EN', 'unit' => 'kg', 'origin' => 'EN', 'variety' => 'EN', 'taste_profile' => 'EN'],
                    // fr missing
                ],
            ])
            ->assertRedirect(route('app.admin.products.create'))
            ->assertSessionHasErrors([
                'translations.fr.name',
                'translations.fr.unit',
                'translations.fr.origin',
                'translations.fr.variety',
                'translations.fr.taste_profile',
            ]);
    }
}
