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

    public function test_unpublished_product_is_excluded_from_shop_index(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Draft Only Tomato',
            'is_available' => true,
            'is_published' => false,
        ]);
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Live Lettuce',
            'is_available' => true,
            'is_published' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.shop.index'));

        $response->assertOk()
            ->assertSee('Live Lettuce', false)
            ->assertDontSee('Draft Only Tomato', false);
    }

    public function test_unpublished_product_detail_returns_404(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => false,
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

    public function test_shop_rejects_invalid_sort(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.index', ['sort' => 'invalid']))
            ->assertSessionHasErrors('sort');
    }

    public function test_shop_rejects_query_longer_than_120_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.index', ['q' => str_repeat('a', 121)]))
            ->assertSessionHasErrors('q');
    }

    public function test_shop_index_shows_empty_state_when_search_matches_no_products(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Visible Product',
            'is_available' => true,
            'is_published' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['q' => 'ZZZ_NO_MATCH_ZZZ']));

        $response->assertOk();
        $response->assertSee('data-testid="empty-state"', false);
        $response->assertSee('No products available', false);
    }

    public function test_shop_search_filters_by_product_name(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Unique Apple Crisp',
            'is_available' => true,
        ]);
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Other Product',
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['q' => 'Unique Apple']));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(1, $products->total());
        $this->assertSame('Unique Apple Crisp', $products->first()->name);
    }

    public function test_shop_sorts_by_price_ascending(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'High Price',
            'unit_price' => 9.99,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Low Price',
            'unit_price' => 1.50,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Mid Price',
            'unit_price' => 4.00,
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['sort' => 'price_asc']));

        $response->assertOk();
        $products = $response->viewData('products');
        $prices = $products->pluck('unit_price')->map(fn ($p) => (float) $p)->all();
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);
        $this->assertSame(1.5, (float) $products->first()->unit_price);
    }

    public function test_shop_sorts_by_price_descending(): void
    {
        $producer = Producer::factory()->create();
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Low Price',
            'unit_price' => 1.00,
            'is_available' => true,
        ]);
        Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'High Price',
            'unit_price' => 12.00,
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', ['sort' => 'price_desc']));

        $response->assertOk();
        $products = $response->viewData('products');
        $this->assertSame(12.0, (float) $products->first()->unit_price);
        $this->assertSame(1.0, (float) $products->last()->unit_price);
    }

    public function test_shop_chips_preserve_search_and_sort_query_params(): void
    {
        $producer1 = Producer::factory()->create(['name' => 'Farm Alpha']);
        $producer2 = Producer::factory()->create(['name' => 'Farm Beta']);
        Product::factory()->create([
            'producer_id' => $producer1->id,
            'category' => 'vegetables',
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('app.shop.index', [
                'category' => 'vegetables',
                'q' => 'carrot',
                'sort' => 'price_asc',
                'producer' => $producer1->id,
            ]));

        $response->assertOk();
        $response->assertSee('q=carrot', false);
        $response->assertSee('sort=price_asc', false);
        $response->assertSee('category=vegetables', false);
        $response->assertSee('producer='.$producer2->id, false);
    }

    public function test_product_detail_shows_characteristics_and_dietary_notes_from_metadata(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Meta Salad Mix',
            'is_available' => true,
            'metadata' => [
                'unit' => 'g',
                'characteristics' => ['Organic', 'Regional'],
                'dietary_notes' => 'May contain traces of nuts.',
            ],
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.show', $product))
            ->assertOk()
            ->assertSee('Organic', false)
            ->assertSee('Regional', false)
            ->assertSee('May contain traces of nuts.', false);
    }

    public function test_product_detail_without_editable_box_shows_guidance(): void
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'name' => 'Solo Tomato',
            'is_available' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.shop.show', $product))
            ->assertOk()
            ->assertSee('Extras need an editable box', false)
            ->assertSee(route('app.boxes.current'), false)
            ->assertSee(route('app.dashboard'), false);
    }
}
