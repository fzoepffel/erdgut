<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeasonWindowTest extends TestCase
{
    use RefreshDatabase;

    private function basePayload(int $producerId, array $overrides = []): array
    {
        return array_merge([
            'producer_id' => $producerId,
            'translations' => [
                'de' => ['name' => 'Spargel', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Zart.', 'short_description' => 'Kurz'],
                'en' => ['name' => 'Asparagus', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Tender.', 'short_description' => 'Short'],
                'fr' => ['name' => 'Asperge', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'Tendre.', 'short_description' => 'Court'],
            ],
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'price' => '9.90',
            'is_published' => '1',
            'is_available' => '1',
        ], $overrides);
    }

    public function test_create_persists_season_window(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->basePayload($producer->id, [
                'is_seasonal' => '1',
                'season_start_month' => '4', 'season_start_day' => '1',
                'season_end_month' => '6', 'season_end_day' => '24',
            ]))
            ->assertRedirect();

        $product = Product::query()->where('producer_id', $producer->id)->firstOrFail();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_seasonal' => true,
            'season_start_month' => 4,
            'season_start_day' => 1,
            'season_end_month' => 6,
            'season_end_day' => 24,
        ]);
    }

    public function test_validation_rejects_out_of_range_month_and_day(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->basePayload($producer->id, [
                'is_seasonal' => '1',
                'season_start_month' => '13', 'season_start_day' => '1',
                'season_end_month' => '6', 'season_end_day' => '32',
            ]))
            ->assertSessionHasErrors(['season_start_month', 'season_end_day']);
    }

    public function test_validation_rejects_partial_window_when_seasonal(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->basePayload($producer->id, [
                'is_seasonal' => '1',
                'season_start_month' => '4', 'season_start_day' => '1',
                // end omitted
            ]))
            ->assertSessionHasErrors(['season_end_month', 'season_end_day']);
    }

    public function test_validation_rejects_invalid_calendar_day(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        // 31 Feb is not a real date.
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->basePayload($producer->id, [
                'is_seasonal' => '1',
                'season_start_month' => '2', 'season_start_day' => '31',
                'season_end_month' => '6', 'season_end_day' => '24',
            ]))
            ->assertSessionHasErrors('season_start_day');
    }

    public function test_turning_off_seasonal_clears_the_window(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'is_seasonal' => true,
            'season_start_month' => 4, 'season_start_day' => 1,
            'season_end_month' => 6, 'season_end_day' => 24,
        ]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->basePayload($product->producer_id, [
                // is_seasonal omitted → false
                'season_start_month' => '4', 'season_start_day' => '1',
                'season_end_month' => '6', 'season_end_day' => '24',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_seasonal' => false,
            'season_start_month' => null,
            'season_start_day' => null,
            'season_end_month' => null,
            'season_end_day' => null,
        ]);
    }
}
