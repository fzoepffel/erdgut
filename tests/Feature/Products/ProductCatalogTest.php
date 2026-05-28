<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.products.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_authenticated_user_sees_only_published_available_products(): void
    {
        $user = User::factory()->create();
        $visible = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $visible->translations()->where('locale', 'en')->update(['name' => 'Visible Apple']);
        $unpublished = Product::factory()->create(['is_available' => true, 'is_published' => false]);
        $unpublished->translations()->where('locale', 'en')->update(['name' => 'Hidden Carrot']);
        $unavailable = Product::factory()->create(['is_available' => false, 'is_published' => true]);
        $unavailable->translations()->where('locale', 'en')->update(['name' => 'Unavailable Beet']);

        $this->actingAs($user)
            ->get(route('app.products.index'))
            ->assertOk()
            ->assertSee('Visible Apple')
            ->assertDontSee('Hidden Carrot')
            ->assertDontSee('Unavailable Beet');
    }

    public function test_unpublished_product_show_returns_404(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => false,
        ]);

        $this->actingAs($user)
            ->get(route('app.products.show', $product))
            ->assertNotFound();
    }

    public function test_available_published_product_show_renders(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Organic Spinach']);

        $this->actingAs($user)
            ->get(route('app.products.show', $product))
            ->assertOk()
            ->assertSee('Organic Spinach');
    }

    public function test_products_index_renders_editorial_layout(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['is_available' => true, 'is_published' => true]);

        $html = $this->actingAs($user)
            ->get(route('app.products.index'))
            ->assertOk()
            ->getContent();

        // Editorial redesign landmarks
        $this->assertStringNotContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-editorial-footer-band"', $html);
        $this->assertStringContainsString('data-testid="products-grid"', $html);
    }

    public function test_products_index_shows_category_rail(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.products.index'))
            ->assertOk()
            ->assertSee('data-testid="products-chapter-tile-all"', false);
    }

    public function test_products_index_empty_catalogue_shows_editorial_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.products.index'))
            ->assertOk()
            ->assertSee('data-testid="empty-state"', false);
    }
}
