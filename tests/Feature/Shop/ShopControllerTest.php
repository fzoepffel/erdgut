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
}
