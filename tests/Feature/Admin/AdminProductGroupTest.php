<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin side of product groups: assign via the product form, bundle
 * duplicates via the products-index bulk action, and clean up empty groups.
 */
class AdminProductGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Producer::factory()->create();
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ], $overrides));
    }

    private function formPayload(Product $product, array $overrides = []): array
    {
        $t = ['name' => (string) $product->name, 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'x'];

        return array_merge([
            'producer_ids' => [$product->producer_id],
            'translations' => ['de' => $t, 'en' => $t, 'fr' => $t],
            'category' => $product->category,
            'unit' => $product->unit->value,
            'price_display_unit' => $product->price_display_unit,
            'price' => '4.00',
        ], $overrides);
    }

    public function test_product_form_creates_a_new_group_and_assigns_it(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)
            ->put(route('app.admin.products.update', $product), $this->formPayload($product, [
                'group_id' => 'new',
                'new_group_name' => ['de' => 'Apfelsäfte', 'en' => 'Apple juices'],
                'group_label' => ['de' => '0,5 l Flasche'],
                'group_position' => 2,
            ]))
            ->assertRedirect();

        $product->refresh();
        $this->assertNotNull($product->group_id);
        $this->assertSame('Apfelsäfte', $product->group->localizedName('de'));
        $this->assertSame('Apple juices', $product->group->localizedName('en'));
        $this->assertSame('0,5 l Flasche', $product->localizedGroupLabel('de'));
        $this->assertSame(2, $product->group_position);
    }

    public function test_leaving_a_group_deletes_it_when_memberless(): void
    {
        $group = ProductGroup::create(['name' => ['de' => 'Solo-Gruppe']]);
        $product = $this->product(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->put(route('app.admin.products.update', $product), $this->formPayload($product, [
                'group_id' => '',
            ]))
            ->assertRedirect();

        $this->assertNull($product->refresh()->group_id);
        $this->assertDatabaseMissing('product_groups', ['id' => $group->id]);
    }

    public function test_bulk_assign_creates_group_from_selection(): void
    {
        $a = $this->product();
        $b = $this->product();

        $this->actingAs($this->admin)
            ->post(route('app.admin.products.group-assign'), [
                'product_ids' => [$a->id, $b->id],
                'group_id' => 'new',
                'new_group_name' => ['de' => 'Zucchini'],
            ])
            ->assertRedirect(route('app.admin.products.index'));

        $group = ProductGroup::query()->latest('id')->firstOrFail();
        $this->assertSame('Zucchini', $group->localizedName('de'));
        $this->assertSame($group->id, $a->refresh()->group_id);
        $this->assertSame($group->id, $b->refresh()->group_id);
        // Selection order becomes group order.
        $this->assertLessThan($b->group_position, $a->group_position);
        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'product_group',
            'entity_id' => $group->id,
            'action' => 'product_group_assign',
        ]);
    }

    public function test_bulk_assign_into_existing_group_cleans_up_emptied_group(): void
    {
        $oldGroup = ProductGroup::create(['name' => ['de' => 'Alt']]);
        $target = ProductGroup::create(['name' => ['de' => 'Ziel']]);
        $existingMember = $this->product(['group_id' => $target->id, 'group_position' => 0]);
        $mover = $this->product(['group_id' => $oldGroup->id]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.products.group-assign'), [
                'product_ids' => [$mover->id],
                'group_id' => (string) $target->id,
            ])
            ->assertRedirect();

        $this->assertSame($target->id, $mover->refresh()->group_id);
        // New member queues behind the existing one.
        $this->assertGreaterThan($existingMember->group_position, $mover->group_position);
        // The emptied previous group is removed.
        $this->assertDatabaseMissing('product_groups', ['id' => $oldGroup->id]);
    }

    public function test_index_shows_group_badge_and_bulk_controls(): void
    {
        $group = ProductGroup::create(['name' => ['de' => 'Beeren-Mix']]);
        $product = $this->product(['group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.products.index'))
            ->assertOk()
            ->assertSee('data-testid="admin-product-group-badge-'.$product->id.'"', false)
            ->assertSee('Beeren-Mix')
            ->assertSee('data-testid="admin-product-group-check-'.$product->id.'"', false)
            ->assertSee('data-testid="admin-products-group-assign-form"', false);
    }

    public function test_bulk_assign_requires_admin(): void
    {
        $product = $this->product();

        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.products.group-assign'), [
                'product_ids' => [$product->id],
                'group_id' => 'new',
                'new_group_name' => ['de' => 'X'],
            ])
            ->assertForbidden();
    }
}
