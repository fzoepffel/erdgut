<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Duplicate-name advisory on the product CREATE form (P5): typing a
 * known name suggests extending the existing product (with a link to
 * its edit page) instead of creating a twin. Advisory only; the edit
 * form carries no payload.
 */
class AdminProductDuplicateWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_ships_existing_names_and_warning_scaffolding(): void
    {
        $admin = User::factory()->admin()->create();
        $farm = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farm->id]);
        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $product->id, 'locale' => 'de'],
            ['name' => 'Bio-Karotten', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'y'],
        );

        $html = $this->actingAs($admin)
            ->get(route('app.admin.products.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('window.erdgutExistingProducts', $html);
        $this->assertStringContainsString('Bio-Karotten', $html);
        $this->assertStringContainsString($farm->name, $html);
        // @json escapes slashes inside the script payload.
        $this->assertStringContainsString(str_replace('/', '\\/', route('app.admin.products.edit', $product)), $html);
        $this->assertStringContainsString('product-duplicate-warning', $html);
    }

    public function test_edit_form_carries_no_duplicate_payload(): void
    {
        $admin = User::factory()->admin()->create();
        Producer::factory()->create();
        $product = Product::factory()->create();

        $html = $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('window.erdgutExistingProducts', $html);
        $this->assertStringNotContainsString('product-duplicate-warning', $html);
    }
}
