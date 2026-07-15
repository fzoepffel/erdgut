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
        // Deterministic name: faker farm names with apostrophes serialize as
        // &#039; in the JSON payload and flake raw-string assertions.
        $farm->translations()->update(['name' => 'Duplikat Hof Alpha']);
        $farm->refresh();
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

        // The Alpine scaffolding is always present (a blade @if inside a
        // component tag breaks compilation), but the edit page must not
        // ship the payload ASSIGNMENT, so the check finds nothing.
        $this->assertStringNotContainsString('window.erdgutExistingProducts =', $html);
    }
}
