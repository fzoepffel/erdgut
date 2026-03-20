<?php

namespace Tests\Feature\Shop;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_shop(): void
    {
        $this->get(route('app.shop.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_authenticated_user_can_view_shop_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.index'))
            ->assertOk()
            ->assertSee('Shop extras');
    }

    public function test_shop_paginates_products(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->count(30)->create([
            'producer_id' => $producer->id,
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index'));

        $response->assertOk();
        $response->assertViewHas('products');
        $products = $response->viewData('products');
        $this->assertSame(24, $products->count());
        $this->assertSame(30, $products->total());
    }

    public function test_shop_filters_by_category(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->count(3)->create([
            'producer_id' => $producer->id,
            'category' => 'vegetables',
            'is_available' => true,
        ]);
        Product::factory()->count(2)->create([
            'producer_id' => $producer->id,
            'category' => 'fruit',
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['category' => 'vegetables']));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(3, $products->total());
        foreach ($products as $product) {
            $this->assertSame('vegetables', $product->category);
        }
    }

    public function test_shop_filters_by_producer(): void
    {
        $producer1 = Producer::factory()->create(['name' => 'Farm A']);
        $producer2 = Producer::factory()->create(['name' => 'Farm B']);
        Product::factory()->count(2)->create([
            'producer_id' => $producer1->id,
            'is_available' => true,
        ]);
        Product::factory()->count(3)->create([
            'producer_id' => $producer2->id,
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['producer' => $producer1->id]));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(2, $products->total());
        foreach ($products as $product) {
            $this->assertSame($producer1->id, $product->producer_id);
        }
    }

    public function test_shop_rejects_invalid_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.index', ['category' => 'invalid']))
            ->assertSessionHasErrors('category');
    }

    public function test_shop_rejects_invalid_producer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.index', ['producer' => 99999]))
            ->assertSessionHasErrors('producer');
    }

    public function test_shop_page_renders_category_chips(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index'));

        $response->assertOk()
            ->assertSee(__('All categories'), false)
            ->assertSee(__('Vegetables'), false)
            ->assertSee(__('Fruit'), false)
            ->assertSee(__('Dairy'), false)
            ->assertSee(__('Bakery'), false);

        $response->assertSee(route('app.shop.index', ['category' => 'vegetables']), false);
        $response->assertSee(route('app.shop.index', ['category' => 'fruit']), false);
    }

    public function test_shop_category_chips_filter_products(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->count(2)->create([
            'producer_id' => $producer->id,
            'category' => 'dairy',
            'is_available' => true,
        ]);
        Product::factory()->count(3)->create([
            'producer_id' => $producer->id,
            'category' => 'bakery',
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['category' => 'dairy']));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(2, $products->total());
        foreach ($products as $product) {
            $this->assertSame('dairy', $product->category);
        }
    }

    public function test_shop_page_renders_producer_chips(): void
    {
        $producer = Producer::factory()->create(['name' => 'Hof Testgut']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index'));

        $response->assertOk()
            ->assertSee(__('All producers'), false)
            ->assertSee('Hof Testgut', false)
            ->assertSee(route('app.shop.index', ['producer' => $producer->id]), false);
    }

    public function test_shop_producer_chips_preserve_category_filter(): void
    {
        $producer1 = Producer::factory()->create(['name' => 'Farm Alpha']);
        $producer2 = Producer::factory()->create(['name' => 'Farm Beta']);
        Product::factory()->count(2)->create([
            'producer_id' => $producer1->id,
            'category' => 'vegetables',
            'is_available' => true,
        ]);
        Product::factory()->count(1)->create([
            'producer_id' => $producer2->id,
            'category' => 'vegetables',
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['category' => 'vegetables', 'producer' => $producer1->id]));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(2, $products->total());
        foreach ($products as $product) {
            $this->assertSame('vegetables', $product->category);
            $this->assertSame($producer1->id, $product->producer_id);
        }

        $response->assertSee('category=vegetables', false);
        $response->assertSee('producer='.$producer2->id, false);
    }

    public function test_shop_page_renders_many_products_without_breaks(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Aardvark Apples',
            'is_available' => true,
        ]);
        Product::factory()->count(34)->create([
            'producer_id' => $producer->id,
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index'));

        $response->assertOk()
            ->assertSee('Aardvark Apples', false)
            ->assertSee('EUR', false);
    }

    public function test_authenticated_user_can_view_product_detail_page(): void
    {
        $producer = Producer::factory()->create(['name' => 'Hof Testgut', 'region' => 'Baden-Württemberg']);
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Bio-Karotten',
            'category' => 'vegetables',
            'unit_price' => 2.49,
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.show', $product));

        $response->assertOk()
            ->assertSee('Bio-Karotten', false)
            ->assertSee('Hof Testgut', false)
            ->assertSee('EUR 2.49', false);
    }

    public function test_unavailable_product_returns_404(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => false,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.show', $product))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_product_detail(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
        ]);

        $this->get(route('app.shop.show', $product))
            ->assertRedirect(route('auth.login'));
    }
}
