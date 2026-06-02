<?php

namespace Tests\Feature\Admin;

use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductImage;
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
        $producer = Producer::factory()->create();
        $producer->translations()->where('locale', 'en')->update(['name' => 'Alpha Farm Co']);
        $zebra = Product::factory()->create(['producer_id' => $producer->id]);
        $zebra->translations()->where('locale', 'en')->update(['name' => 'Zebra Apples']);
        $other = Product::factory()->create();
        $other->translations()->where('locale', 'en')->update(['name' => 'Other Pears']);

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
        $fruit = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'is_published' => true,
        ]);
        $fruit->translations()->where('locale', 'en')->update(['name' => 'Only Fruit Row']);

        $veg = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'is_published' => false,
        ]);
        $veg->translations()->where('locale', 'en')->update(['name' => 'Veg Row']);

        $this->actingAs($admin)
            ->get(route('app.admin.products.index', [
                'category' => 'produce',
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
                'translations' => [
                    'de' => ['name' => 'Admin Test Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Leicht süß und knackig.'],
                    'en' => ['name' => 'Admin Test Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Lightly sweet and crisp.'],
                    'fr' => ['name' => 'Admin Test Apples', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'Légèrement sucrées et croquantes.'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '2.50',
            ])
            ->assertRedirect();

        $product = Product::query()
            ->where('producer_id', $producer->id)
            ->where('category', 'produce')
            ->firstOrFail();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'producer_id' => $producer->id,
            'category' => 'produce',
            // Admin entered €2.50/kg; storage canonical is €/g, so 0.0025.
            'unit_price' => 0.0025,
            'price_display_unit' => 'kg',
            'unit' => 'mass',
            'is_available' => false,
            'is_published' => true,
            'is_seasonal' => false,
            'is_test' => false,
        ]);

        $this->assertDatabaseHas('product_translations', [
            'product_id' => $product->id,
            'locale' => 'en',
            'name' => 'Admin Test Apples',
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
                'translations' => [
                    'de' => ['name' => 'With Image', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'With Image', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'With Image', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'is_available' => '1',
                'image' => $file,
            ])
            ->assertRedirect();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'With Image'))
            ->firstOrFail();
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
                'translations' => [
                    'de' => ['name' => 'Blocked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'Blocked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'Blocked', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Hijacked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'Hijacked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'Hijacked', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '9.99',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('app.admin.products.destroy', $product))
            ->assertForbidden();
    }

    public function test_admin_can_save_attributes_and_buying_price(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Attr Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'süß'],
                    'en' => ['name' => 'Attr Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'sweet'],
                    'fr' => ['name' => 'Attr Apples', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'sucré'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '2.50',
                'buying_price' => '1.10',
                'allergens' => ['gluten', 'nuts'],
                'dietary_tags' => ['vegan', 'vegetarian'],
                'taste_profile_tags' => ['sweet', 'crisp'],
            ])
            ->assertRedirect();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'Attr Apples'))
            ->firstOrFail();

        $this->assertSame(110, $product->buying_price_cents);
        $this->assertSame(['gluten', 'nuts'], $product->allergens);
        $this->assertSame(['vegan', 'vegetarian'], $product->dietary_tags);
        $this->assertSame(['sweet', 'crisp'], $product->taste_profile_tags);
    }

    public function test_admin_can_save_trilingual_custom_taste_tags(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Custom Tags', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x'],
                    'en' => ['name' => 'Custom Tags', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x'],
                    'fr' => ['name' => 'Custom Tags', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'x'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '2.50',
                // A predefined enum tag plus a trilingual custom one.
                'taste_profile_tags' => ['sweet'],
                'custom_taste_tags' => [
                    ['de' => 'Buttrig', 'en' => 'Buttery', 'fr' => 'Beurré'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'Custom Tags'))
            ->firstOrFail();

        $this->assertSame(['sweet'], $product->taste_profile_tags);
        $this->assertSame(
            [['de' => 'Buttrig', 'en' => 'Buttery', 'fr' => 'Beurré']],
            $product->custom_taste_tags
        );

        // Custom tag resolves to the active locale, falling back through de.
        $this->assertSame(['Buttery'], $product->localizedCustomTasteTags('en'));
        $this->assertSame(['Beurré'], $product->localizedCustomTasteTags('fr'));
        $this->assertSame(['Buttrig'], $product->localizedCustomTasteTags('de'));
    }

    public function test_admin_custom_taste_tag_requires_all_three_languages(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Partial Tag', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x'],
                    'en' => ['name' => 'Partial Tag', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x'],
                    'fr' => ['name' => 'Partial Tag', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'x'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '2.50',
                // Missing the FR label — must be rejected.
                'custom_taste_tags' => [
                    ['de' => 'Buttrig', 'en' => 'Buttery', 'fr' => ''],
                ],
            ])
            ->assertSessionHasErrors('custom_taste_tags.0.fr');
    }

    public function test_admin_update_syncs_pairings(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);
        $partnerA = Product::factory()->create();
        $partnerB = Product::factory()->create();

        $product->pairings()->sync([$partnerA->id]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'pairings' => [$partnerB->id],
            ])
            ->assertRedirect();

        $pairedIds = $product->fresh()->pairings()->pluck('products.id')->all();
        sort($pairedIds);
        $this->assertSame([$partnerB->id], $pairedIds);
    }

    public function test_admin_cannot_pair_product_with_itself(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'pairings' => [$product->id],
            ])
            ->assertSessionHasErrors('pairings.0');
    }

    public function test_admin_can_upload_gallery_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);
        $a = UploadedFile::fake()->image('a.jpg', 200, 200);
        $b = UploadedFile::fake()->image('b.jpg', 200, 200);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'G', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'G', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'G', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'gallery_images' => [$a, $b],
            ])
            ->assertRedirect();

        $this->assertSame(2, $product->fresh()->images()->count());
        foreach ($product->fresh()->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_admin_can_remove_gallery_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);

        Storage::disk('public')->put('products/'.$product->id.'/x.jpg', 'binary');
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/'.$product->id.'/x.jpg',
            'position' => 0,
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'R', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'R', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'R', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
                'gallery_remove' => [$image->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/'.$product->id.'/x.jpg');
    }

    public function test_admin_can_update_product_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit_price' => 1.00,
            'is_available' => true,
        ]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'New Name', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'en' => ['name' => 'New Name', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—'],
                    'fr' => ['name' => 'New Name', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '3.25',
                'is_available' => '1',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product));

        $product->refresh();
        $this->assertSame('New Name', $product->name);
        $this->assertSame('produce', $product->category);
        // Admin entered €3.25/kg → canonical storage is €/g. Cast is
        // `decimal:4` so 0.00325 rounds to 0.0033.
        $this->assertEqualsWithDelta(0.00325, (float) $product->unit_price, 0.0001);
        $this->assertSame('kg', $product->price_display_unit);
    }
}
