<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopClosedDay;
use App\Models\User;
use App\Services\Shop\ShopClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopDeliveryClosureTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    private const OPEN_DATE = '2026-06-06';

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));

        $this->admin = User::factory()->admin()->create();

        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        foreach ([self::DELIVERY_DATE, self::OPEN_DATE] as $date) {
            ProductStockInflow::create([
                'product_id' => $this->product->id,
                'received_on' => $date,
                'quantity' => 10,
                'freshness_window_days' => 1,
            ]);
        }

        ShopClosedDay::query()->create([
            'closed_on' => self::DELIVERY_DATE,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_closed_date_absent_from_storefront_date_picker(): void
    {
        $response = $this->get(route('public.shop.index'));

        $response->assertOk();
        $response->assertSee(self::OPEN_DATE, false);
        $response->assertDontSee('value="'.self::DELIVERY_DATE.'"', false);
    }

    public function test_store_rejects_order_on_closed_date(): void
    {
        $this->post(route('public.shop.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'quantities' => [$this->product->id => 1],
            'consent' => '1',
        ])->assertSessionHasErrors('expected_delivery_date');
    }

    public function test_admin_can_create_order_on_closed_date_when_stock_exists(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), [
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DELIVERY_DATE,
                'customer_type' => 'private',
                'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            ])
            ->assertRedirect();
    }

    public function test_storefront_falls_back_when_requested_date_is_closed(): void
    {
        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($this->product->name);
    }

    public function test_admin_can_toggle_day_via_calendar_endpoint(): void
    {
        ShopClosedDay::query()->delete();

        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.toggle'), [
                'date' => self::DELIVERY_DATE,
                'month' => '2026-06',
            ])
            ->assertOk()
            ->assertJsonPath('closed', true);

        $this->assertTrue(app(ShopClosureService::class)->isClosed(Carbon::parse(self::DELIVERY_DATE)));

        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.toggle'), [
                'date' => self::DELIVERY_DATE,
                'month' => '2026-06',
            ])
            ->assertOk()
            ->assertJsonPath('closed', false);

        $this->assertFalse(app(ShopClosureService::class)->isClosed(Carbon::parse(self::DELIVERY_DATE)));
    }

    public function test_closure_calendar_page_renders_month_grid(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-closures.index', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('closureCalendar', false)
            ->assertSee(__('admin.shop_closures.calendar_heading'))
            ->assertDontSee(__('admin.shop_closures.create_heading'));
    }

    public function test_purge_removes_past_closed_days_on_index(): void
    {
        ShopClosedDay::query()->delete();

        ShopClosedDay::query()->create([
            'closed_on' => '2026-05-25',
            'created_by_user_id' => $this->admin->id,
        ]);

        ShopClosedDay::query()->create([
            'closed_on' => self::DELIVERY_DATE,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-closures.index'))
            ->assertOk();

        $this->assertDatabaseMissing('shop_closed_days', ['closed_on' => '2026-05-25']);
        $this->assertDatabaseHas('shop_closed_days', ['closed_on' => self::DELIVERY_DATE]);
    }

    public function test_cannot_toggle_past_day(): void
    {
        ShopClosedDay::query()->delete();

        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.toggle'), [
                'date' => '2026-05-28',
                'month' => '2026-05',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_calendar_marks_today_and_past_as_disabled(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson(route('app.admin.shop-closures.calendar', ['month' => '2026-05']));

        $cells = collect($response->json('weeks'))->flatten(1);
        $today = $cells->firstWhere('iso', '2026-05-30');
        $past = $cells->firstWhere('iso', '2026-05-28');
        $tomorrow = $cells->firstWhere('iso', '2026-05-31');

        $this->assertTrue($today['is_disabled']);
        $this->assertTrue($past['is_disabled']);
        $this->assertFalse($tomorrow['is_disabled']);
    }

    public function test_past_days_are_implicitly_closed_without_a_row(): void
    {
        ShopClosedDay::query()->delete();

        $this->assertTrue(app(ShopClosureService::class)->isClosed(Carbon::parse('2026-05-28')));
        $this->assertDatabaseCount('shop_closed_days', 0);
    }
}
