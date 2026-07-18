<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Product groups on the storefront: several full products (different farms,
 * even different UNITS) render as ONE card with a grade per member. Cart and
 * order lines stay keyed (product_id, variant_id).
 */
class ProductGroupStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    private ProductGroup $group;

    private Product $massMember;   // sold per kg

    private Product $pieceMember;  // sold per Stück

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
        Producer::factory()->create();

        $this->group = ProductGroup::create(['name' => ['de' => 'Zucchini-Auswahl'], 'position' => 0]);

        $this->massMember = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.004, // 4,00 €/kg gross
            'is_available' => true, 'is_published' => true,
            'group_id' => $this->group->id,
            'group_position' => 0,
            'group_label' => ['de' => 'lose (kg)'],
        ]);
        $this->pieceMember = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 1.50,
            'is_available' => true, 'is_published' => true,
            'group_id' => $this->group->id,
            'group_position' => 1,
        ]);

        foreach ([[$this->massMember, 5000], [$this->pieceMember, 10]] as [$product, $qty]) {
            ProductStockInflow::create([
                'product_id' => $product->id,
                'received_on' => self::DELIVERY_DATE,
                'quantity' => $qty,
                'freshness_window_days' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_service_collapses_members_into_one_row(): void
    {
        $rows = app(ShopOrderService::class)->weeklyProductsForDate(Carbon::parse(self::DELIVERY_DATE));

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($this->massMember->id, $row['product']->id); // lead by group_position
        $this->assertSame($this->group->id, $row['group']->id);
        $this->assertCount(2, $row['members']);
        $this->assertTrue($row['available_on_selected']);
    }

    public function test_availability_index_collapses_members_to_the_lead(): void
    {
        $index = app(ShopOrderService::class)->availabilityIndex(2);

        $dateRow = $index['dates']->firstWhere(fn ($d) => $d['date']->toDateString() === self::DELIVERY_DATE);
        $this->assertNotNull($dateRow);
        $this->assertSame(1, $dateRow['product_count']);
        $this->assertSame([$this->massMember->id], $dateRow['product_ids']);

        $this->assertCount(1, $index['products']);
        $this->assertSame('Zucchini-Auswahl', $index['products'][0]['name']);
    }

    public function test_card_renders_group_name_and_both_member_grades(): void
    {
        $response = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee('Zucchini-Auswahl')
            // Both members' hidden quantity inputs live in the ONE card.
            ->assertSee('name="quantities['.$this->massMember->id.']"', false)
            ->assertSee('name="quantities['.$this->pieceMember->id.']"', false)
            // Member grade labels: explicit group_label and producer fallback.
            ->assertSee('lose (kg)');

        // Members must not render as standalone cards: the piece member's
        // own product name appears only in the registry, not as a card title.
        $html = $response->getContent();
        $this->assertSame(
            1,
            substr_count($html, 'Zucchini-Auswahl</a>'),
            'exactly one grouped card title link'
        );
    }

    public function test_checkout_creates_one_line_per_member_with_its_own_unit(): void
    {
        $payload = [
            'name' => 'Tina Tester', 'email' => 'tina@example.net', 'phone' => '030 123',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'quantities' => [
                (string) $this->massMember->id => 1000, // 1 kg canonical g
                (string) $this->pieceMember->id => 2,
            ],
        ];

        $order = app(ShopOrderService::class)->createOrder(array_merge($payload, [
            'items' => [
                ['product_id' => $this->massMember->id, 'variant_id' => null, 'quantity' => 1000],
                ['product_id' => $this->pieceMember->id, 'variant_id' => null, 'quantity' => 2],
            ],
            'payment_method' => 'in_person',
        ]));

        $this->assertInstanceOf(ShopOrder::class, $order);
        $items = $order->items()->orderBy('product_id')->get();
        $this->assertCount(2, $items);
        $this->assertSame('mass', $items->firstWhere('product_id', $this->massMember->id)->unit);
        $this->assertSame('piece', $items->firstWhere('product_id', $this->pieceMember->id)->unit);
        $this->assertEqualsWithDelta(4.00, (float) $items->firstWhere('product_id', $this->massMember->id)->line_total_eur, 0.001);
        $this->assertEqualsWithDelta(3.00, (float) $items->firstWhere('product_id', $this->pieceMember->id)->line_total_eur, 0.001);
    }

    public function test_partial_availability_shows_only_stocked_member_grades(): void
    {
        // Drain the piece member's stock: only the kg member's grade remains.
        ProductStockInflow::query()->where('product_id', $this->pieceMember->id)->delete();

        $rows = app(ShopOrderService::class)->weeklyProductsForDate(Carbon::parse(self::DELIVERY_DATE));
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()['available_on_selected']);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee('Zucchini-Auswahl')
            ->assertSee('name="quantities['.$this->massMember->id.']"', false)
            ->assertDontSee('name="quantities['.$this->pieceMember->id.']"', false);
    }
}
