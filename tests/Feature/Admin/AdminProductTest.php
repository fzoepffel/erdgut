<?php

namespace Tests\Feature\Admin;

use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_admin_products_index(): void
    {
        $this->get(route('app.admin.products.index'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden_on_admin_products_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.products.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_products_index(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('app.admin.products.index'))
            ->assertOk()
            ->assertSee('Products', false);
    }

    public function test_admin_products_index_filters_by_search_query(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create(['name' => 'Alpha Farm Co']);
        Product::factory()->create(['name' => 'Zebra Apples', 'producer_id' => $producer->id]);
        Product::factory()->create(['name' => 'Other Pears']);

        $this->actingAs($admin)
            ->get(route('app.admin.products.index', ['q' => 'Zebra']))
            ->assertOk()
            ->assertSee('Zebra Apples', false)
            ->assertDontSee('Other Pears', false);

        $this->actingAs($admin)
            ->get(route('app.admin.products.index', ['q' => 'Alpha Farm']))
            ->assertOk()
            ->assertSee('Zebra Apples', false);
    }

    public function test_admin_products_index_filters_by_category_and_published(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Only Fruit Row',
            'category' => 'fruit',
            'is_published' => true,
        ]);
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Veg Row',
            'category' => 'vegetables',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.products.index', [
                'category' => 'fruit',
                'published' => '1',
            ]))
            ->assertOk()
            ->assertSee('Only Fruit Row', false)
            ->assertDontSee('Veg Row', false);

        $this->actingAs($admin)
            ->get(route('app.admin.products.index', ['published' => '0']))
            ->assertOk()
            ->assertSee('Veg Row', false)
            ->assertDontSee('Only Fruit Row', false);
    }

    public function test_admin_products_index_rejects_oversized_search_query(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.index'))
            ->get(route('app.admin.products.index', ['q' => str_repeat('a', 121)]))
            ->assertRedirect(route('app.admin.products.index'))
            ->assertSessionHasErrors('q');
    }

    public function test_admin_products_index_rejects_invalid_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.index'))
            ->get(route('app.admin.products.index', ['category' => 'not-a-real-category']))
            ->assertRedirect(route('app.admin.products.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_admin_can_create_product_via_form_post(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'name' => 'Admin Test Apples',
                'category' => 'fruit',
                'unit_price' => '2.50',
                'metadata' => [
                    'unit' => 'kg',
                    'origin' => 'test-region',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'producer_id' => $producer->id,
            'name' => 'Admin Test Apples',
            'category' => 'fruit',
            'unit_price' => 2.5,
            'is_available' => false,
            'is_published' => true,
            'is_seasonal' => false,
            'is_test' => false,
        ]);
    }

    public function test_admin_can_store_product_with_image_on_public_disk(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $file = UploadedFile::fake()->image('catalog.jpg', 120, 120);

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'name' => 'With Image',
                'category' => 'vegetables',
                'unit_price' => '1.00',
                'is_available' => '1',
                'image' => $file,
            ])
            ->assertRedirect();

        $product = Product::query()->where('name', 'With Image')->firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_admin_cannot_delete_product_referenced_by_box_item(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        BoxItem::factory()->create(['product_id' => $product->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.products.index'))
            ->delete(route('app.admin.products.destroy', $product))
            ->assertRedirect(route('app.admin.products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admin_can_delete_product_with_no_box_items(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.index'))
            ->delete(route('app.admin.products.destroy', $product))
            ->assertRedirect(route('app.admin.products.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_non_admin_does_not_see_products_nav_link(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee(route('app.admin.products.index', absolute: false), false);
    }

    public function test_non_admin_receives_forbidden_on_product_store_update_destroy(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);

        $this->actingAs($user)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'name' => 'Blocked',
                'category' => 'fruit',
                'unit_price' => '1.00',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'name' => 'Hijacked',
                'category' => 'fruit',
                'unit_price' => '9.99',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('app.admin.products.destroy', $product))
            ->assertForbidden();
    }

    public function test_admin_can_update_product_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Old Name',
            'category' => 'vegetables',
            'unit_price' => 1.00,
            'is_available' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'name' => 'New Name',
                'category' => 'fruit',
                'unit_price' => '3.25',
                'is_available' => '1',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product));

        $product->refresh();
        $this->assertSame('New Name', $product->name);
        $this->assertSame('fruit', $product->category);
        $this->assertSame('3.25', $product->unit_price);
    }
}
