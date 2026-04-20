<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminWeeklyCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('app.admin.products.weekly.show'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->actingAs($user)
            ->get(route('app.admin.products.weekly.show'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_open_weekly_catalog_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.products.weekly.show'))
            ->assertOk()
            ->assertSee(__('admin.weekly_catalog.title'));
    }

    public function test_week_navigator_snaps_non_monday_to_previous_monday(): void
    {
        $admin = User::factory()->admin()->create();

        // 2026-03-25 is a Wednesday → should snap to Monday 2026-03-23.
        $this->actingAs($admin)
            ->get(route('app.admin.products.weekly.show', ['week' => '2026-03-25']))
            ->assertOk()
            ->assertSee('23.03.2026');
    }

    public function test_save_creates_weekly_stock_for_existing_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $monday = Carbon::parse('2026-04-06')->toDateString();

        $this->actingAs($admin)
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [
                    [
                        'mode' => 'existing',
                        'product_id' => $product->id,
                        'quantity' => 42.5,
                        'unit' => 'kg',
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.products.weekly.show', ['week' => $monday]))
            ->assertSessionHas('status');

        $stock = ProductWeekStock::query()
            ->where('product_id', $product->id)
            ->whereDate('week_start_date', $monday)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEqualsWithDelta(42.5, (float) $stock->planned_quantity, 0.001);
        $this->assertSame('kg', $stock->unit);
    }

    public function test_save_upserts_existing_stock_row(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $monday = Carbon::parse('2026-04-06')->toDateString();

        $existing = ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday,
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [
                    [
                        'mode' => 'existing',
                        'stock_id' => $existing->id,
                        'product_id' => $product->id,
                        'quantity' => 22,
                        'unit' => 'kg',
                    ],
                ],
            ])
            ->assertRedirect();

        $existing->refresh();
        $this->assertEqualsWithDelta(22.0, (float) $existing->planned_quantity, 0.001);
        $this->assertSame('kg', $existing->unit);
    }

    public function test_save_creates_new_product_inline(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $monday = Carbon::parse('2026-04-06')->toDateString();

        $this->actingAs($admin)
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [
                    [
                        'mode' => 'new',
                        'quantity' => 12,
                        'unit' => 'bundle',
                        'new' => [
                            'name' => 'Fresh Inline Carrots',
                            'producer_id' => $producer->id,
                            'category' => 'vegetables',
                            'unit_price_cents' => 199,
                            'is_published' => '1',
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('name', 'Fresh Inline Carrots')->first();
        $this->assertNotNull($product);
        $this->assertEqualsWithDelta(1.99, (float) $product->unit_price, 0.001);
        $this->assertTrue($product->is_published);

        $stock = ProductWeekStock::query()
            ->where('product_id', $product->id)
            ->whereDate('week_start_date', $monday)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEqualsWithDelta(12.0, (float) $stock->planned_quantity, 0.001);
    }

    public function test_save_delete_removes_stock_row_but_not_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $monday = Carbon::parse('2026-04-06')->toDateString();

        $stock = ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday,
            'planned_quantity' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [
                    [
                        'mode' => 'existing',
                        'stock_id' => $stock->id,
                        'product_id' => $product->id,
                        'quantity' => 10,
                        '_delete' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertNull(ProductWeekStock::query()->find($stock->id));
        $this->assertNotNull(Product::query()->find($product->id));
    }

    public function test_save_rejects_duplicate_product_rows_in_same_week(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $monday = Carbon::parse('2026-04-06')->toDateString();

        $this->actingAs($admin)
            ->from(route('app.admin.products.weekly.show', ['week' => $monday]))
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [
                    [
                        'mode' => 'existing',
                        'product_id' => $product->id,
                        'quantity' => 5,
                    ],
                    [
                        'mode' => 'existing',
                        'product_id' => $product->id,
                        'quantity' => 7,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame(0, ProductWeekStock::query()->count());
    }

    public function test_over_allocation_does_not_block_save(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $monday = Carbon::parse('2026-04-06')->toDateString();

        $this->actingAs($admin)
            ->post(route('app.admin.products.weekly.save', ['week' => $monday]), [
                'week_start_date' => $monday,
                'rows' => [
                    [
                        'mode' => 'existing',
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, ProductWeekStock::query()->count());
    }
}
