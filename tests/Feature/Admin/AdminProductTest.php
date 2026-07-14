<?php

namespace Tests\Feature\Admin;

use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductStockInflow;
use App\Models\ProductSubcategory;
use App\Models\ProductTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function test_count_unit_label_persists_for_piece_and_clears_on_kind_switch(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        // Store a piece product with the display word.
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Basilikum im Topf', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'Frisch.', 'short_description' => 'Kurz'],
                    'en' => ['name' => 'Potted basil', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'Fresh.', 'short_description' => 'Kurz'],
                    'fr' => ['name' => 'Basilic en pot', 'origin' => 'régional', 'variety' => 'x', 'taste_profile' => 'Frais.', 'short_description' => 'Kurz'],
                ],
                'category' => 'produce',
                'unit' => 'piece',
                'price_display_unit' => 'piece',
                'price' => '3.50',
                'count_unit_label' => 'pot',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame('pot', $product->count_unit_label?->value);

        // Switching the kind to mass clears the word.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Basilikum im Topf', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'Frisch.', 'short_description' => 'Kurz'],
                    'en' => ['name' => 'Potted basil', 'origin' => 'regional', 'variety' => 'x', 'taste_profile' => 'Fresh.', 'short_description' => 'Kurz'],
                    'fr' => ['name' => 'Basilic en pot', 'origin' => 'régional', 'variety' => 'x', 'taste_profile' => 'Frais.', 'short_description' => 'Kurz'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '12.00',
                'count_unit_label' => 'pot',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($product->refresh()->count_unit_label);

        // Unknown word rejected.
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'category' => 'produce',
                'unit' => 'piece',
                'price_display_unit' => 'piece',
                'price' => '3.50',
                'count_unit_label' => 'barrel',
            ])
            ->assertSessionHasErrors('count_unit_label');
    }

    public function test_admin_can_create_product_via_form_post(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Admin Test Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Leicht süß und knackig.', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Admin Test Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Lightly sweet and crisp.', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Admin Test Apples', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'Légèrement sucrées et croquantes.', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'With Image', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'With Image', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'With Image', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'Blocked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Blocked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Blocked', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'Hijacked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Hijacked', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Hijacked', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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

    public function test_admin_can_save_mass_product_with_per_100g_price_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => '100g Apples', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'kurz'],
                    'en' => ['name' => '100g Apples', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'short'],
                    'fr' => ['name' => '100g Apples', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'court'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => '100g',
                'price' => '2.15', // €2,15 per 100 g
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', '100g Apples'))
            ->firstOrFail();

        // €2.15 / 100 g → canonical €/g = 0.0215.
        $this->assertSame('100g', $product->price_display_unit);
        $this->assertEqualsWithDelta(0.0215, (float) $product->unit_price, 0.0001);

        // Customer-facing price renders in the chosen 100 g unit.
        $this->assertSame('2,15 €/100 g', $product->displayPrice('de'));
    }

    public function test_admin_can_store_product_with_standard_vat_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Apfelsaft', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'kurz'],
                    'en' => ['name' => 'Apfelsaft', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'short'],
                    'fr' => ['name' => 'Apfelsaft', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'court'],
                ],
                'category' => 'produce',
                'unit' => 'piece',
                'price_display_unit' => 'piece',
                'price' => '3.50',
                'vat_rate_percent' => '19',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'Apfelsaft'))
            ->firstOrFail();

        $this->assertSame(19, $product->vat_rate_percent);
    }

    public function test_product_defaults_to_reduced_vat_rate_when_not_submitted(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Möhren', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'kurz'],
                    'en' => ['name' => 'Möhren', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'short'],
                    'fr' => ['name' => 'Möhren', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'court'],
                ],
                'category' => 'produce',
                'unit' => 'piece',
                'price_display_unit' => 'piece',
                'price' => '1.20',
                // vat_rate_percent intentionally omitted.
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'Möhren'))
            ->firstOrFail();

        $this->assertSame(7, $product->vat_rate_percent);
    }

    public function test_admin_can_update_product_vat_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['vat_rate_percent' => 7]);

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $product->producer_id,
                'translations' => [
                    'de' => ['name' => 'X', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'kurz'],
                    'en' => ['name' => 'X', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'short'],
                    'fr' => ['name' => 'X', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'court'],
                ],
                'category' => $product->category,
                'unit' => $product->unit->value,
                'price_display_unit' => $product->price_display_unit,
                'price' => '2.00',
                'vat_rate_percent' => '19',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(19, $product->fresh()->vat_rate_percent);
    }

    public function test_short_description_is_required_in_all_locales(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.create'))
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    // de has a short description, en/fr omit it.
                    'de' => ['name' => 'X', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long', 'short_description' => 'kurz'],
                    'en' => ['name' => 'X', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long'],
                    'fr' => ['name' => 'X', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'long'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '1.00',
            ])
            ->assertRedirect(route('app.admin.products.create'))
            ->assertSessionHasErrors([
                'translations.en.short_description',
                'translations.fr.short_description',
            ]);
    }

    public function test_admin_can_save_short_and_long_descriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Desc Apples', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'Lange Beschreibung DE', 'short_description' => 'Kurz DE'],
                    'en' => ['name' => 'Desc Apples', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'Long description EN', 'short_description' => 'Short EN'],
                    'fr' => ['name' => 'Desc Apples', 'origin' => 'r', 'variety' => '—', 'taste_profile' => 'Longue description FR', 'short_description' => 'Court FR'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '2.50',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::query()
            ->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'Desc Apples'))
            ->firstOrFail();

        $this->assertSame('Short EN', $product->localizedShortDescription('en'));
        $this->assertSame('Long description EN', $product->localizedTasteProfile('en'));
        // Card prefers the short description.
        $this->assertSame('Short EN', $product->cardDescription('en'));
    }

    public function test_card_description_falls_back_to_long_when_short_is_empty(): void
    {
        // Simulates a product migrated before short descriptions existed:
        // short_description is null, so the card must fall back to the long one.
        $product = Product::factory()->create();
        $product->translations()->where('locale', 'en')->update([
            'short_description' => null,
            'taste_profile' => 'Fallback long description',
        ]);
        $product->refresh();

        $this->assertSame('', $product->localizedShortDescription('en'));
        $this->assertSame('Fallback long description', $product->cardDescription('en'));
    }

    public function test_admin_can_save_attributes_and_buying_price(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Attr Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'süß', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Attr Apples', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'sweet', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Attr Apples', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'sucré', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'Custom Tags', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Custom Tags', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Custom Tags', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'Partial Tag', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Partial Tag', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Partial Tag', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'Kurzbeschreibung'],
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

    public function test_pairings_are_reciprocal_on_add_and_remove(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);
        $partnerA = Product::factory()->create();
        $partnerB = Product::factory()->create();

        $payload = fn (array $pairings): array => [
            'producer_id' => $producer->id,
            'translations' => [
                'de' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                'en' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                'fr' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
            ],
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'price' => '1.00',
            'pairings' => $pairings,
        ];

        // Adding A + B to the product mirrors the pairing onto A and B.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $payload([$partnerA->id, $partnerB->id]))
            ->assertRedirect();
        $this->assertSame([$product->id], $partnerA->fresh()->pairings()->pluck('products.id')->all());
        $this->assertSame([$product->id], $partnerB->fresh()->pairings()->pluck('products.id')->all());

        // Removing B from the product also removes the mirror on B.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $payload([$partnerA->id]))
            ->assertRedirect();
        $this->assertSame([$product->id], $partnerA->fresh()->pairings()->pluck('products.id')->all());
        $this->assertSame([], $partnerB->fresh()->pairings()->pluck('products.id')->all());

        // Backfill migration: a one-directional legacy row gains its inverse
        // (note copied, since the note describes the pairing itself).
        $legacyA = Product::factory()->create();
        $legacyB = Product::factory()->create();
        DB::table('product_pairings')->insert([
            'product_id' => $legacyA->id, 'paired_product_id' => $legacyB->id,
            'note' => 'passt zu Salat', 'created_at' => now(), 'updated_at' => now(),
        ]);
        (include database_path('migrations/2026_07_07_120000_backfill_reciprocal_product_pairings.php'))->up();
        $inverse = DB::table('product_pairings')
            ->where('product_id', $legacyB->id)->where('paired_product_id', $legacyA->id)->first();
        $this->assertNotNull($inverse);
        $this->assertSame('passt zu Salat', $inverse->note);
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
                    'de' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'X', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'G', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'G', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'G', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'R', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'R', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'R', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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
                    'de' => ['name' => 'New Name', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'New Name', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'New Name', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
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

    public function test_admin_update_normalizes_json_and_html_escapes_in_descriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit_price' => 1.00,
        ]);

        $corrupted = 'Fraîches avec une note d\\u0027asperge — l\\u0027épluchage est rarement nécessaire.';
        $expected = "Fraîches avec une note d'asperge — l'épluchage est rarement nécessaire.";

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Spargel', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => $corrupted, 'short_description' => 'this week&#039;s harvest'],
                    'en' => ['name' => 'Asparagus', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => $corrupted, 'short_description' => 'this week&#039;s harvest'],
                    'fr' => ['name' => 'Asperges', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => $corrupted, 'short_description' => 'this week&#039;s harvest'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '3.25',
                'is_available' => '1',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product));

        $fr = ProductTranslation::query()
            ->where('product_id', $product->id)
            ->where('locale', 'fr')
            ->first();

        $this->assertSame($expected, $fr->taste_profile);
        $this->assertSame("this week's harvest", $fr->short_description);
    }

    public function test_edit_form_prefills_stored_price_display_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => 'mass',
            'price_display_unit' => 'kg',
        ]);

        // The Alpine x-data must seed `displayUnit` from the stored value so
        // the "Preis pro" select keeps its choice across edits instead of
        // resetting to empty. Regression guard for the option/x-model race.
        $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->assertSee("displayUnit: 'kg'", escape: false);
    }

    public function test_admin_can_assign_subcategory_on_product_update(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $subcategory = ProductSubcategory::query()->create([
            'category' => 'produce',
            'label_de' => 'Beeren',
            'label_en' => 'Berries',
            'label_fr' => 'Baies',
            'sort_order' => 10,
        ]);
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'subcategory_id' => null,
            'unit_price' => 1.00,
        ]);

        $translations = [
            'de' => ['name' => 'Berry Product', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
            'en' => ['name' => 'Berry Product', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
            'fr' => ['name' => 'Berry Product', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
        ];

        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => $translations,
                'category' => 'produce',
                'subcategory_id' => (string) $subcategory->id,
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '3.25',
                'is_available' => '1',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product));

        $this->assertSame($subcategory->id, $product->refresh()->subcategory_id);
    }

    public function test_admin_cannot_assign_subcategory_from_wrong_category(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $subcategory = ProductSubcategory::query()->create([
            'category' => 'meat',
            'label_de' => 'Rind',
            'label_en' => 'Beef',
            'label_fr' => 'Bœuf',
            'sort_order' => 10,
        ]);
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit_price' => 1.00,
        ]);

        $translations = [
            'de' => ['name' => 'Mismatch', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
            'en' => ['name' => 'Mismatch', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
            'fr' => ['name' => 'Mismatch', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
        ];

        $this->actingAs($admin)
            ->from(route('app.admin.products.edit', $product))
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => $translations,
                'category' => 'produce',
                'subcategory_id' => (string) $subcategory->id,
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '3.25',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product))
            ->assertSessionHasErrors('subcategory_id');

        $this->assertNull($product->refresh()->subcategory_id);
    }

    public function test_edit_form_seeds_subcategory_id_for_alpine_select(): void
    {
        $admin = User::factory()->admin()->create();
        $subcategory = ProductSubcategory::query()->create([
            'category' => 'produce',
            'label_de' => 'Wurzeln',
            'label_en' => 'Roots',
            'label_fr' => 'Racines',
            'sort_order' => 20,
        ]);
        $product = Product::factory()->create([
            'category' => 'produce',
            'subcategory_id' => $subcategory->id,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->assertSee('name="subcategory_id"', escape: false)
            ->assertSee('subcategoryId: '.$subcategory->id, escape: false)
            ->assertSee('x-ref="subcategorySelect"', escape: false);
    }

    // ── Unit-kind change vs. existing inventory ───────────────────────────

    /**
     * Changing the unit KIND (mass↔bunch etc.) while stock inflows exist is
     * blocked: the stored amounts are in the old kind's canonical unit and
     * would be silently misread under the new kind. Regression for the
     * goliath-rhubarb bunch→mass inventory corruption.
     */
    public function test_unit_kind_change_is_blocked_when_stock_inflow_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => 'bunch',
            'price_display_unit' => 'bunch',
            'unit_price' => 3.00,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-06-12',
            'quantity' => 4, // 4 bunches
            'freshness_window_days' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.products.edit', $product))
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Goliath', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Goliath', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Goliath', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                ],
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'price' => '5.00',
                'quantity_step' => '500',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product))
            ->assertSessionHasErrors('unit');

        // Unit unchanged in DB.
        $this->assertSame('bunch', $product->refresh()->unit->value);
    }

    /**
     * With no inventory, switching kinds (mass→bunch) succeeds even though
     * the form still submits a stale quantity_step (hidden, not cleared by
     * Alpine). The controller nulls out the irrelevant field. Regression for
     * the failed save + 500 on re-render.
     */
    public function test_unit_kind_change_succeeds_when_no_inventory_and_clears_stale_step(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'unit_price' => 0.005,
            'quantity_step' => 500,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.products.edit', $product))
            ->put(route('app.admin.products.update', $product), [
                'producer_id' => $producer->id,
                'translations' => [
                    'de' => ['name' => 'Switched', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'en' => ['name' => 'Switched', 'unit' => 'kg', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                    'fr' => ['name' => 'Switched', 'unit' => 'kg', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => '—', 'short_description' => 'Kurzbeschreibung'],
                ],
                'category' => 'produce',
                'unit' => 'bunch',
                'price_display_unit' => 'bunch',
                'price' => '3.00',
                // Stale value carried by the hidden mass-only field.
                'quantity_step' => '500',
                // New bunch descriptor.
                'unit_weight_grams' => '350',
            ])
            ->assertRedirect(route('app.admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('bunch', $product->unit->value);
        $this->assertNull($product->quantity_step);          // cleared
        $this->assertSame(350, $product->unit_weight_grams);  // kept
    }

    /**
     * The edit form must render (200, not 500) even when `old()` input holds
     * a display unit from a different kind than the persisted product —
     * the state right after a blocked unit-kind change. Regression for the
     * `canonicalUnitsPerDisplay()` throw in the price prefill.
     */
    public function test_edit_form_renders_after_failed_unit_kind_switch(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'unit_price' => 0.005,
        ]);

        // Simulate the post-validation-failure redirect: old() carries the
        // bunch display unit while the product is still stored as mass.
        $this->actingAs($admin)
            ->withSession(['_old_input' => [
                'unit' => 'bunch',
                'price_display_unit' => 'bunch',
            ]])
            ->get(route('app.admin.products.edit', $product))
            ->assertOk();
    }
}
