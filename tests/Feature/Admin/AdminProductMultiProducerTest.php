<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductProducerVariantPrice;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-producer product form (P5): ordered producer_ids[] drive the
 * product_producer pivot; the first id is the primary farm mirrored
 * into products.producer_id. Legacy single-producer_id posts keep
 * working via the FormRequest compat mapping.
 */
class AdminProductMultiProducerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'translations' => [
                'de' => ['name' => 'Multi-Hof-Karotten', 'origin' => 'regional', 'variety' => 'Nantaise', 'taste_profile' => 'Süß und knackig.', 'short_description' => 'Kurz'],
                'en' => ['name' => 'Multi farm carrots', 'origin' => 'regional', 'variety' => 'Nantaise', 'taste_profile' => 'Sweet and crisp.', 'short_description' => 'Kurz'],
                'fr' => ['name' => 'Carottes multi-fermes', 'origin' => 'régional', 'variety' => 'Nantaise', 'taste_profile' => 'Douces et croquantes.', 'short_description' => 'Kurz'],
            ],
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'price' => '2.50',
        ], $overrides);
    }

    /** @return array<int, array{producer_id: int, position: int}> */
    private function pivotRows(Product $product): array
    {
        return $product->producers()->get()
            ->map(fn (Producer $p): array => [
                'producer_id' => $p->id,
                'position' => (int) $p->pivot->position,
            ])->all();
    }

    public function test_store_persists_ordered_producers_and_mirrors_the_first(): void
    {
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload([
                'producer_ids' => [$farmB->id, $farmA->id],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame($farmB->id, (int) $product->producer_id);
        $this->assertSame([
            ['producer_id' => $farmB->id, 'position' => 0],
            ['producer_id' => $farmA->id, 'position' => 1],
        ], $this->pivotRows($product));
    }

    public function test_update_reorders_and_flips_the_primary_mirror(): void
    {
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farmB->id]);
        $product->syncProducers([$farmB->id, $farmA->id]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farmA->id, $farmB->id],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame($farmA->id, (int) $product->producer_id);
        $this->assertSame([
            ['producer_id' => $farmA->id, 'position' => 0],
            ['producer_id' => $farmB->id, 'position' => 1],
        ], $this->pivotRows($product));
    }

    public function test_update_detaches_removed_farms(): void
    {
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farmA->id]);
        $product->syncProducers([$farmA->id, $farmB->id]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farmA->id],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [['producer_id' => $farmA->id, 'position' => 0]],
            $this->pivotRows($product->refresh()),
        );
    }

    public function test_legacy_single_producer_id_posts_still_work(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload([
                'producer_id' => $farm->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame($farm->id, (int) $product->producer_id);
        $this->assertSame(
            [['producer_id' => $farm->id, 'position' => 0]],
            $this->pivotRows($product),
        );
    }

    public function test_store_persists_per_farm_buying_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload([
                'producer_ids' => [$farmA->id, $farmB->id],
                'producer_buying_prices' => [$farmA->id => '2.50', $farmB->id => ''],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('product_producer', [
            'product_id' => $product->id, 'producer_id' => $farmA->id, 'buying_price_cents' => 250,
        ]);
        $this->assertDatabaseHas('product_producer', [
            'product_id' => $product->id, 'producer_id' => $farmB->id, 'buying_price_cents' => null,
        ]);
    }

    public function test_update_clears_a_price_when_the_field_is_emptied(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        $product->producers()->updateExistingPivot($farm->id, ['buying_price_cents' => 275]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farm->id],
                'producer_buying_prices' => [$farm->id => ''],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_producer', [
            'product_id' => $product->id, 'producer_id' => $farm->id, 'buying_price_cents' => null,
        ]);
    }

    public function test_edit_form_prefills_per_farm_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        $product->producers()->updateExistingPivot($farm->id, ['buying_price_cents' => 250]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('product-form-producer-prices', $html);
        $this->assertStringContainsString('prices: {"'.$farm->id.'":"2.50"}', $html);
    }

    public function test_update_persists_and_clears_grade_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => ['de' => 'Premium']]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farm->id],
                'producer_variant_buying_prices' => [$farm->id => [$variant->id => '3.20']],
                'variants' => [
                    ['id' => $variant->id, 'name' => ['de' => 'Premium', 'en' => '', 'fr' => ''], 'unit_price' => '9.99', 'is_active' => '1'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_producer_variant_prices', [
            'product_id' => $product->id, 'producer_id' => $farm->id,
            'variant_id' => $variant->id, 'buying_price_cents' => 320,
        ]);

        // Empty clears the override.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farm->id],
                'producer_variant_buying_prices' => [$farm->id => [$variant->id => '']],
                'variants' => [
                    ['id' => $variant->id, 'name' => ['de' => 'Premium', 'en' => '', 'fr' => ''], 'unit_price' => '9.99', 'is_active' => '1'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('product_producer_variant_prices', [
            'product_id' => $product->id, 'producer_id' => $farm->id, 'variant_id' => $variant->id,
        ]);
    }

    public function test_removing_a_farm_drops_its_grade_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farmA->id]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => ['de' => 'Premium']]);
        ProductProducerVariantPrice::create([
            'product_id' => $product->id, 'producer_id' => $farmB->id,
            'variant_id' => $variant->id, 'buying_price_cents' => 300,
        ]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farmA->id], // farm B removed
                'variants' => [
                    ['id' => $variant->id, 'name' => ['de' => 'Premium', 'en' => '', 'fr' => ''], 'unit_price' => '9.99', 'is_active' => '1'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('product_producer_variant_prices', ['producer_id' => $farmB->id]);
    }

    public function test_grade_price_validation_rejects_unknown_grade_and_farm(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $other = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        $foreignVariant = ProductVariant::factory()->create(); // another product's grade

        // Unknown grade.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farm->id],
                'producer_variant_buying_prices' => [$farm->id => [$foreignVariant->id => '2.00']],
            ]))
            ->assertSessionHasErrors('producer_variant_buying_prices');

        // Unselected farm.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->payload([
                'producer_ids' => [$farm->id],
                'producer_variant_buying_prices' => [$other->id => []],
            ]))
            ->assertSessionHasErrors('producer_variant_buying_prices');
    }

    public function test_edit_form_prefills_grade_prices_and_create_shows_none(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => ['de' => 'Premium']]);
        ProductProducerVariantPrice::create([
            'product_id' => $product->id, 'producer_id' => $farm->id,
            'variant_id' => $variant->id, 'buying_price_cents' => 275,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('product-form-producer-grade-price', $html);
        $this->assertStringContainsString('"'.$farm->id.'_'.$variant->id.'":"2.75"', $html);

        $createHtml = $this->actingAs($admin)
            ->get(route('app.admin.products.create'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('product-form-producer-grade-price', $createHtml);
    }

    public function test_validation_rejects_prices_for_unselected_farms(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $other = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload([
                'producer_ids' => [$farm->id],
                'producer_buying_prices' => [$other->id => '2.00'],
            ]))
            ->assertSessionHasErrors('producer_buying_prices');
    }

    public function test_validation_rejects_empty_unknown_and_duplicate_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload(['producer_ids' => []]))
            ->assertSessionHasErrors('producer_ids');

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload(['producer_ids' => [999999]]))
            ->assertSessionHasErrors('producer_ids.0');

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->payload(['producer_ids' => [$farm->id, $farm->id]]))
            ->assertSessionHasErrors('producer_ids.0');
    }
}
