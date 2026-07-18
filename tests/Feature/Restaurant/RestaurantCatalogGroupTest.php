<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductStockInflow;
use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Product groups in the B2B catalog: member products (different farms,
 * different units) merge into ONE row whose grades each keep their OWN
 * unit symbol, step, cap, net price and productId.
 */
class RestaurantCatalogGroupTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private ProductGroup $group;

    private Product $massMember;

    private Product $pieceMember;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
        $this->date = Carbon::parse('2026-06-05');
        Producer::factory()->create();

        $this->group = ProductGroup::create(['name' => ['de' => 'Kräuter-Mix'], 'position' => 0]);

        $this->massMember = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.004,
            'vat_rate_percent' => 7,
            'is_seasonal' => false,
            'is_available' => true, 'is_published' => true,
            'group_id' => $this->group->id, 'group_position' => 0,
            'group_label' => ['de' => 'lose'],
        ]);
        $this->pieceMember = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 1.50,
            'vat_rate_percent' => 7,
            // Fixed: a seasonal member without stock would legitimately stay
            // visible as a request-track grade and break the drop assertion.
            'is_seasonal' => false,
            'is_available' => true, 'is_published' => true,
            'group_id' => $this->group->id, 'group_position' => 1,
        ]);

        foreach ([[$this->massMember, 5000], [$this->pieceMember, 10]] as [$product, $qty]) {
            ProductStockInflow::create([
                'product_id' => $product->id,
                'received_on' => $this->date->toDateString(),
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

    public function test_members_merge_into_one_row_with_per_grade_units(): void
    {
        $rows = app(RestaurantCatalogService::class)->productsForDate($this->date);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($this->massMember->id, $row['product']->id);
        $this->assertSame('Kräuter-Mix', $row['groupName']);
        $this->assertTrue($row['inStock']);

        $grades = collect($row['grades'])->keyBy('productId');
        $this->assertCount(2, $grades);

        $massGrade = $grades[$this->massMember->id];
        $pieceGrade = $grades[$this->pieceMember->id];
        // Each grade keeps ITS member's unit and stock semantics.
        $this->assertSame('kg', $massGrade['unitSymbol']);
        $this->assertSame('mass', $massGrade['unitKind']);
        $this->assertEqualsWithDelta(5000, $massGrade['cap'], 0.001);
        $this->assertSame('piece', $pieceGrade['unitKind']);
        $this->assertEqualsWithDelta(10, $pieceGrade['cap'], 0.001);
        // Grade labels: explicit group_label vs producer fallback.
        $this->assertSame('lose', $massGrade['label']);
        $this->assertNotSame('', (string) $pieceGrade['label']);
        // Keys stay globally unique (member product ids).
        $this->assertSame((string) $this->massMember->id, $massGrade['key']);
        $this->assertSame((string) $this->pieceMember->id, $pieceGrade['key']);
    }

    public function test_headline_is_the_cheapest_member_with_price_from_flag(): void
    {
        $rows = app(RestaurantCatalogService::class)->productsForDate($this->date);
        $row = $rows->first();

        // kg member: 4,00 €/kg gross → net 3,74; piece member: 1,50 gross →
        // net 1,40. Cheapest per display unit is the piece member.
        $this->assertEqualsWithDelta(1.40, (float) $row['netPerDisplay'], 0.01);
        $this->assertTrue($row['priceFrom']);
        $this->assertSame('piece', $row['displayUnit']);
    }

    public function test_group_row_survives_when_only_one_member_has_stock(): void
    {
        ProductStockInflow::query()->where('product_id', $this->pieceMember->id)->delete();

        $rows = app(RestaurantCatalogService::class)->productsForDate($this->date);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertTrue($row['inStock']);
        $this->assertCount(1, $row['grades']);
        $this->assertSame($this->massMember->id, $row['grades'][0]['productId']);
    }
}
