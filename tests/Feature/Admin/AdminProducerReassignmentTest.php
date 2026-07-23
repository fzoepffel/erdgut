<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Http\Controllers\Admin\AdminShopOrderController;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductSubcategory;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Admin\ProducerReassignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Manual sourcing moves in the receiving panel: a position covered by several
 * farms can be relocated to another farm (or a look-alike product), and the
 * panel plus the farmer export honor the override without touching the order.
 */
class AdminProducerReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-07-15';

    private Producer $farmA;

    private Producer $farmB;

    private Producer $farmC;

    private Product $zucchini;      // primary A, supplied by A and B

    private Product $zucchiniGelb;  // look-alike, primary B

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00:00'));

        $this->farmA = $this->farm('Farm Alpha');
        $this->farmB = $this->farm('Farm Beta');
        $this->farmC = $this->farm('Farm Gamma');

        $this->zucchini = Product::factory()->create([
            'producer_id' => $this->farmA->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $this->zucchini->translations()->update(['name' => 'Zucchini gruen']);
        $this->zucchini = $this->zucchini->fresh();
        // Both farms can supply the zucchini (pivot drives the target label).
        $this->zucchini->producers()->sync([$this->farmA->id, $this->farmB->id]);

        $this->zucchiniGelb = Product::factory()->create([
            'producer_id' => $this->farmB->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $this->zucchiniGelb->translations()->update(['name' => 'Zucchini gelb']);
        $this->zucchiniGelb = $this->zucchiniGelb->fresh();

        // Stock: A has 5, B has 20 zucchini; B has 20 zucchini gelb; C has only 1.
        $this->inflow($this->zucchini, $this->farmA, 5);
        $this->inflow($this->zucchini, $this->farmB, 20);
        $this->inflow($this->zucchiniGelb, $this->farmB, 20);

        // A firm shop order for 3 zucchini.
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $this->zucchini->id,
            'quantity' => 3, 'unit' => 'piece', 'line_total_eur' => 9.0, 'vat_amount_eur' => 0.59,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function farm(string $name): Producer
    {
        $p = Producer::factory()->create();
        $p->translations()->update(['name' => $name]);

        return $p->fresh();
    }

    private function inflow(Product $product, Producer $producer, float $qty): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $producer->id,
            'received_on' => self::DATE, 'quantity' => $qty, 'freshness_window_days' => 2,
        ]);
    }

    /**
     * A piece product with distinct names per locale, sourced from one farm.
     *
     * @param  array{de: string, en: string, fr: string}  $names
     */
    private function namedProduct(Producer $farm, array $names): Product
    {
        $p = Product::factory()->create([
            'producer_id' => $farm->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 3.0,
            'is_available' => true, 'is_published' => true,
        ]);
        foreach ($names as $locale => $name) {
            $p->translations()->where('locale', $locale)->update(['name' => $name]);
        }
        $p = $p->fresh('translations');
        $p->producers()->sync([$farm->id]);

        return $p->fresh('translations');
    }

    private function service(): ProducerReassignmentService
    {
        return app(ProducerReassignmentService::class);
    }

    private function targets(): array
    {
        $qty = $this->service()->positionDemand($this->zucchini->id, 0, self::DATE);
        $current = $this->service()->currentSourcing($this->zucchini->id, 0, self::DATE);

        return $this->service()->moveTargetsForPosition(
            $this->zucchini->fresh(), 0, self::DATE, $qty, $current['product_id'], $current['producer_id'],
        );
    }

    public function test_move_targets_include_other_farm_and_similar_product(): void
    {
        $targets = collect($this->targets());

        // Farm B can take the whole zucchini position (same product).
        $sameProduct = $targets->firstWhere(fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmB->id);
        $this->assertNotNull($sameProduct);
        $this->assertTrue($sameProduct['same_product']);

        // Capacity carries the target product's unit so the panel can label it.
        $this->assertArrayHasKey('unit', $sameProduct);
        $this->assertNotSame('', $sameProduct['unit']);

        // The look-alike "Zucchini gelb" from farm B is offered too.
        $lookalike = $targets->firstWhere(fn ($t) => $t['target_product_id'] === $this->zucchiniGelb->id);
        $this->assertNotNull($lookalike);
        $this->assertFalse($lookalike['same_product']);
        $this->assertSame($this->farmB->id, $lookalike['producer_id']);
    }

    public function test_capacity_is_reported_in_the_products_display_unit(): void
    {
        // A mass product stores inflow in grams; the move target must report
        // capacity in kg (4500 g -> 4,5 kg), not raw canonical grams.
        $farmD = $this->farm('Farm Delta');
        $farmE = $this->farm('Farm Epsilon');

        $cucumber = Product::factory()->create([
            'producer_id' => $farmD->id,
            'unit' => ProductUnit::Mass->value, 'price_display_unit' => 'kg',
            'is_available' => true, 'is_published' => true,
        ]);
        $cucumber->translations()->update(['name' => 'Field Cucumber']);
        $cucumber = $cucumber->fresh();
        $cucumber->producers()->sync([$farmD->id, $farmE->id]);

        $this->inflow($cucumber, $farmD, 4500);   // canonical grams
        $this->inflow($cucumber, $farmE, 4500);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $cucumber->id,
            'quantity' => 400, 'unit' => 'g', 'line_total_eur' => 2.0, 'vat_amount_eur' => 0.13,
        ]);

        $qty = $this->service()->positionDemand($cucumber->id, 0, self::DATE);
        $current = $this->service()->currentSourcing($cucumber->id, 0, self::DATE);
        $targets = collect($this->service()->moveTargetsForPosition(
            $cucumber->fresh(), 0, self::DATE, $qty, $current['product_id'], $current['producer_id'],
        ));

        $target = $targets->firstWhere('producer_id', $farmE->id);
        $this->assertNotNull($target);
        $this->assertEqualsWithDelta(4.5, $target['capacity'], 1e-6);
        $this->assertSame('kg', $target['unit']);
    }

    public function test_farm_without_enough_stock_is_not_a_target(): void
    {
        // Farm C has only 1 piece; it must never appear for a 3-piece position.
        $this->assertNull(collect($this->targets())->firstWhere('producer_id', $this->farmC->id));
    }

    public function test_registered_farm_without_inflow_is_still_a_same_product_target(): void
    {
        // Farm C is a listed supplier of the zucchini (pivot) but has no tracked
        // inflow. A same-product, different-farm move must still be possible.
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmC->id => ['buying_price_cents' => 150]]);

        $target = collect($this->targets())->firstWhere(
            fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmC->id,
        );

        $this->assertNotNull($target);
        $this->assertTrue($target['same_product']);
    }

    public function test_move_targets_carry_buying_price_and_margin_warning(): void
    {
        // Customer keeps the 2,50 € zucchini price. Farm B buys cheaper (1,50),
        // the pricier look-alike drives the margin negative.
        $this->zucchini->update(['unit_price' => 2.5, 'buying_price_cents' => 180]);
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmB->id => ['buying_price_cents' => 150]]);
        $this->zucchiniGelb->update(['unit_price' => 2.5, 'buying_price_cents' => 300]);

        $targets = collect($this->targets());

        $bSame = $targets->firstWhere(
            fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmB->id,
        );
        $this->assertNotNull($bSame);
        $this->assertSame(1.5, $bSame['buying_price']);
        $this->assertSame(1.0, $bSame['margin']);
        $this->assertTrue($bSame['price_differs']);
        $this->assertFalse($bSame['margin_negative']);

        $gelb = $targets->firstWhere(fn ($t) => $t['target_product_id'] === $this->zucchiniGelb->id);
        $this->assertNotNull($gelb);
        $this->assertTrue($gelb['margin_negative']);
    }

    public function test_lookalike_product_from_another_farm_makes_row_movable(): void
    {
        $admin = User::factory()->admin()->create();

        // Two separate products with the SAME name, one farm each. Looke covers
        // its own small order; Melchhof (a look-alike product) has spare stock.
        $looke = Product::factory()->create([
            'producer_id' => $this->farmA->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $looke->translations()->update(['name' => 'Yellow Zucchini']);
        $looke = $looke->fresh();
        $looke->producers()->sync([$this->farmA->id]);

        $melchhof = Product::factory()->create([
            'producer_id' => $this->farmB->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $melchhof->translations()->update(['name' => 'Yellow Zucchini']);
        $melchhof = $melchhof->fresh();
        $melchhof->producers()->sync([$this->farmB->id]);

        $this->inflow($looke, $this->farmA, 2);
        $this->inflow($melchhof, $this->farmB, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $looke->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 5.0, 'vat_amount_eur' => 0.3,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        $a = $producers->firstWhere('id', $this->farmA->id);
        $this->assertNotNull($a);
        $row = collect($a['rows'])->firstWhere('product_id', $looke->id);
        $this->assertNotNull($row);
        // Melchhof's look-alike product can cover the whole Looke position, so
        // the position must be movable even though Looke lists only one farm.
        $this->assertTrue($row['movable']);
    }

    public function test_lookalike_matched_only_via_english_becomes_a_move_target(): void
    {
        // German names never pair ("rucola" vs "senfblatt"), but the English
        // names share a first word ("Wild Rocket" ↔ "Wild Mustard"). Matching
        // across all languages must still surface the candidate as a target.
        $source = $this->namedProduct($this->farmA, ['de' => 'Rucola', 'en' => 'Wild Rocket', 'fr' => 'Roquette']);
        $candidate = $this->namedProduct($this->farmB, ['de' => 'Senfblatt', 'en' => 'Wild Mustard', 'fr' => 'Moutarde']);

        $this->inflow($source, $this->farmA, 2);
        $this->inflow($candidate, $this->farmB, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $source->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 5.0, 'vat_amount_eur' => 0.3,
        ]);

        $qty = $this->service()->positionDemand($source->id, 0, self::DATE);
        $current = $this->service()->currentSourcing($source->id, 0, self::DATE);
        $targets = collect($this->service()->moveTargetsForPosition(
            $source, 0, self::DATE, $qty, $current['product_id'], $current['producer_id'],
        ));

        $lookalike = $targets->firstWhere('target_product_id', $candidate->id);
        $this->assertNotNull($lookalike, 'Candidate matched only via English should be a move target.');
        $this->assertFalse($lookalike['same_product']);
        $this->assertSame($this->farmB->id, $lookalike['producer_id']);
    }

    public function test_low_value_farm_with_replaceable_lines_is_recommended(): void
    {
        $admin = User::factory()->admin()->create();

        // A fresh farm whose whole order is worth €6 (2 × €3), with a capable
        // look-alike elsewhere. No farm holds a fitting order, so the low-value
        // rule fires and points at the capable farm.
        $tinyFarm = $this->farm('Tiny Farm');
        $spareFarm = $this->farm('Spare Farm');
        $onlyLine = $this->namedProduct($tinyFarm, ['de' => 'Gelbe Zucchini', 'en' => 'Yellow Zucchini', 'fr' => 'Courgette jaune']);
        $spare = $this->namedProduct($spareFarm, ['de' => 'Gelbe Zucchini', 'en' => 'Yellow Zucchini', 'fr' => 'Courgette jaune']);

        $this->inflow($onlyLine, $tinyFarm, 2);
        $this->inflow($spare, $spareFarm, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $onlyLine->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 6.0, 'vat_amount_eur' => 0.4,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        $farm = $producers->firstWhere('id', $tinyFarm->id);
        $row = collect($farm['rows'])->firstWhere('product_id', $onlyLine->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['recommended']);
        $this->assertSame('low_value', $row['recommend_reason']);
        $this->assertContains($spareFarm->id, $row['recommend_target_producers']);
    }

    public function test_a_lone_but_valuable_farm_is_not_recommended(): void
    {
        $admin = User::factory()->admin()->create();

        // Same shape as above, but the single line is worth €30 (> €20), so
        // there is no reason to break the farm up: no recommendation.
        $bigFarm = $this->farm('Big Farm');
        $spareFarm = $this->farm('Spare Farm');
        $onlyLine = $this->namedProduct($bigFarm, ['de' => 'Gelbe Zucchini', 'en' => 'Yellow Zucchini', 'fr' => 'Courgette jaune']);
        $spare = $this->namedProduct($spareFarm, ['de' => 'Gelbe Zucchini', 'en' => 'Yellow Zucchini', 'fr' => 'Courgette jaune']);

        $this->inflow($onlyLine, $bigFarm, 10);
        $this->inflow($spare, $spareFarm, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $onlyLine->id,
            'quantity' => 10, 'unit' => 'piece', 'line_total_eur' => 30.0, 'vat_amount_eur' => 2.0,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        $farm = $producers->firstWhere('id', $bigFarm->id);
        $row = collect($farm['rows'])->firstWhere('product_id', $onlyLine->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['movable']);
        $this->assertFalse($row['recommended']);
    }

    public function test_consolidation_recommends_only_the_farm_with_a_fitting_order(): void
    {
        $admin = User::factory()->admin()->create();

        // The user's scenario: Annabelle potatoes at the current farm. Another
        // farm already has a Solist potato order (fitting) and can cover it; a
        // third farm has spare potato stock but NO order. Only the farm with the
        // fitting order must be a recommended (red) target.
        $current = $this->farm('Current Farm');    // Looke
        $holder = $this->farm('Holder Farm');      // Melchhof, has a potato order
        $bystander = $this->farm('Bystander Farm'); // Hübner, capable but no order

        $annabelle = $this->namedProduct($current, ['de' => 'Annabelle Kartoffel', 'en' => 'Annabelle Potato', 'fr' => 'Pomme de terre Annabelle']);
        $solist = $this->namedProduct($holder, ['de' => 'Solist Kartoffel', 'en' => 'Solist Potato', 'fr' => 'Pomme de terre Solist']);
        $laura = $this->namedProduct($bystander, ['de' => 'Laura Kartoffel', 'en' => 'Laura Potato', 'fr' => 'Pomme de terre Laura']);
        // A non-fitting product that keeps the bystander present in the run.
        $carrot = $this->namedProduct($bystander, ['de' => 'Karotte', 'en' => 'Carrot', 'fr' => 'Carotte']);

        $this->inflow($annabelle, $current, 10);
        $this->inflow($solist, $holder, 40);
        $this->inflow($laura, $bystander, 20);
        $this->inflow($carrot, $bystander, 20);

        // Current farm's Annabelle order is worth €30 (> €20), so only the
        // "fitting order elsewhere" rule can apply, not the low-value one.
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $annabelle->id,
            'quantity' => 10, 'unit' => 'piece', 'line_total_eur' => 30.0, 'vat_amount_eur' => 2.0,
        ]);
        // The holder already has its own Solist potato order.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $solist->id,
            'quantity' => 3, 'unit' => 'piece', 'line_total_eur' => 9.0, 'vat_amount_eur' => 0.6,
        ]);
        // The bystander is present in the run via carrots, but has no potato order.
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $carrot->id,
            'quantity' => 5, 'unit' => 'piece', 'line_total_eur' => 15.0, 'vat_amount_eur' => 1.0,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        $farm = $producers->firstWhere('id', $current->id);
        $row = collect($farm['rows'])->firstWhere('product_id', $annabelle->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['recommended']);
        $this->assertSame('consolidate', $row['recommend_reason']);
        // Only the farm with the fitting order is red, never the bystander.
        $this->assertContains($holder->id, $row['recommend_target_producers']);
        $this->assertNotContains($bystander->id, $row['recommend_target_producers']);
    }

    public function test_products_from_the_current_farm_are_never_move_targets(): void
    {
        // Melchhof supplies the ordered "Bush Beans" and also a look-alike
        // "Pole Beans" of its own. Moving within the same farm is pointless, so
        // Melchhof must not appear as a target for its own position.
        $melchhof = $this->farm('Melchhof');
        $other = $this->farm('Other Farm');

        $bush = $this->namedProduct($melchhof, ['de' => 'Buschbohnen', 'en' => 'Bush Beans', 'fr' => 'Haricots nains']);
        $poleSame = $this->namedProduct($melchhof, ['de' => 'Stangenbohnen', 'en' => 'Pole Beans', 'fr' => 'Haricots à rames']);
        $poleOther = $this->namedProduct($other, ['de' => 'Stangenbohnen', 'en' => 'Pole Beans', 'fr' => 'Haricots à rames']);

        $this->inflow($bush, $melchhof, 2);
        $this->inflow($poleSame, $melchhof, 20);
        $this->inflow($poleOther, $other, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $bush->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 6.0, 'vat_amount_eur' => 0.4,
        ]);

        $qty = $this->service()->positionDemand($bush->id, 0, self::DATE);
        $targets = collect($this->service()->moveTargetsForPosition(
            $bush, 0, self::DATE, $qty, $bush->id, $melchhof->id,
        ));

        // Nothing from Melchhof itself, not even its own look-alike product.
        $this->assertTrue($targets->every(fn ($t) => $t['producer_id'] !== $melchhof->id));
        $this->assertNull($targets->firstWhere('target_product_id', $poleSame->id));
        // The other farm's look-alike is still offered.
        $this->assertNotNull($targets->firstWhere('producer_id', $other->id));
    }

    public function test_lookalike_from_a_different_subcategory_is_not_a_target(): void
    {
        $roots = ProductSubcategory::query()->create([
            'category' => 'produce', 'label_de' => 'Wurzeln', 'label_en' => 'Roots', 'label_fr' => 'Racines', 'sort_order' => 10,
        ]);
        $leaves = ProductSubcategory::query()->create([
            'category' => 'produce', 'label_de' => 'Blätter', 'label_en' => 'Leaves', 'label_fr' => 'Feuilles', 'sort_order' => 20,
        ]);

        // Three identically-named products. The source and one substitute sit in
        // "Roots"; a third sits in "Leaves". Only the same-subcategory one may be
        // a target, even though all three names match.
        $source = $this->namedProduct($this->farmA, ['de' => 'Rote Bete', 'en' => 'Beetroot', 'fr' => 'Betterave']);
        $source->update(['subcategory_id' => $roots->id]);
        $source = $source->fresh('translations');

        $sameSub = $this->namedProduct($this->farmB, ['de' => 'Rote Bete', 'en' => 'Beetroot', 'fr' => 'Betterave']);
        $sameSub->update(['subcategory_id' => $roots->id]);

        $wrongSub = $this->namedProduct($this->farmC, ['de' => 'Rote Bete', 'en' => 'Beetroot', 'fr' => 'Betterave']);
        $wrongSub->update(['subcategory_id' => $leaves->id]);

        $this->inflow($source, $this->farmA, 2);
        $this->inflow($sameSub, $this->farmB, 20);
        $this->inflow($wrongSub, $this->farmC, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $source->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 6.0, 'vat_amount_eur' => 0.4,
        ]);

        $qty = $this->service()->positionDemand($source->id, 0, self::DATE);
        $current = $this->service()->currentSourcing($source->id, 0, self::DATE);
        $targets = collect($this->service()->moveTargetsForPosition(
            $source, 0, self::DATE, $qty, $current['product_id'], $current['producer_id'],
        ));

        $this->assertNotNull($targets->firstWhere('target_product_id', $sameSub->id));
        $this->assertNull($targets->firstWhere('target_product_id', $wrongSub->id));
    }

    public function test_low_value_farm_flags_only_the_lines_that_can_move(): void
    {
        $admin = User::factory()->admin()->create();

        // A €12 farm (2 lines × €6), below the threshold. One line has a capable
        // look-alike elsewhere; the other is unique with no replacement anywhere.
        // Only the replaceable line may be flagged, never the whole farm.
        $tinyFarm = $this->farm('Tiny Farm');
        $spareFarm = $this->farm('Spare Farm');

        $movable = $this->namedProduct($tinyFarm, ['de' => 'Gelbe Zucchini', 'en' => 'Yellow Zucchini', 'fr' => 'Courgette jaune']);
        $spare = $this->namedProduct($spareFarm, ['de' => 'Gelbe Zucchini', 'en' => 'Yellow Zucchini', 'fr' => 'Courgette jaune']);
        $stuck = $this->namedProduct($tinyFarm, ['de' => 'Seltene Wurzel', 'en' => 'Rare Root', 'fr' => 'Racine rare']);

        $this->inflow($movable, $tinyFarm, 2);
        $this->inflow($spare, $spareFarm, 20);
        $this->inflow($stuck, $tinyFarm, 2);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $movable->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 6.0, 'vat_amount_eur' => 0.4,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $stuck->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 6.0, 'vat_amount_eur' => 0.4,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        $farm = $producers->firstWhere('id', $tinyFarm->id);
        $movableRow = collect($farm['rows'])->firstWhere('product_id', $movable->id);
        $stuckRow = collect($farm['rows'])->firstWhere('product_id', $stuck->id);

        // The replaceable line is flagged and points at the capable farm.
        $this->assertNotNull($movableRow);
        $this->assertTrue($movableRow['recommended']);
        $this->assertSame('low_value', $movableRow['recommend_reason']);
        $this->assertContains($spareFarm->id, $movableRow['recommend_target_producers']);

        // The unique line has no replacement, so it stays unmarked even though its
        // farm is low-value: we no longer flag a whole farm because one line fits.
        $this->assertNotNull($stuckRow);
        $this->assertFalse($stuckRow['recommended']);
    }

    public function test_targets_report_sufficiency_and_name_closeness(): void
    {
        // Farm C is a registered supplier of the zucchini but has only 1 piece
        // for a 3-piece position: it is still offered, flagged insufficient, and
        // sorted to the very bottom.
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmC->id => ['buying_price_cents' => 150]]);
        $this->inflow($this->zucchini, $this->farmC, 1);

        $targets = $this->targets();

        foreach ($targets as $t) {
            $this->assertArrayHasKey('sufficient', $t);
            $this->assertArrayHasKey('name_score', $t);
        }

        $collected = collect($targets);

        $farmC = $collected->firstWhere('producer_id', $this->farmC->id);
        $this->assertNotNull($farmC);
        $this->assertFalse($farmC['sufficient']);

        $farmB = $collected->firstWhere(
            fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmB->id,
        );
        $this->assertTrue($farmB['sufficient']);

        // The identically-named same product scores higher than the look-alike.
        $gelb = $collected->firstWhere('target_product_id', $this->zucchiniGelb->id);
        $this->assertGreaterThan($gelb['name_score'], $farmB['name_score']);

        // Insufficient targets always sit at the bottom of the server order.
        $this->assertSame($this->farmC->id, $targets[array_key_last($targets)]['producer_id']);
    }

    public function test_move_to_registered_farm_without_inflow_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmC->id => ['buying_price_cents' => 150]]);

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
                'target_product_id' => $this->zucchini->id,
                'target_producer_id' => $this->farmC->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('producer_position_overrides', [
            'product_id' => $this->zucchini->id,
            'target_producer_id' => $this->farmC->id,
        ]);
    }

    public function test_move_endpoint_creates_override_and_reattributes_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
                'target_product_id' => $this->zucchini->id,
                'target_producer_id' => $this->farmB->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('producer_position_overrides', [
            'product_id' => $this->zucchini->id,
            'variant_id' => 0,
            'delivery_date' => self::DATE,
            'target_product_id' => $this->zucchini->id,
            'target_producer_id' => $this->farmB->id,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        // Zucchini is now under farm B (moved), and not under farm A.
        $b = $producers->firstWhere('id', $this->farmB->id);
        $this->assertNotNull($b);
        $bRow = collect($b['rows'])->firstWhere('product_id', $this->zucchini->id);
        $this->assertNotNull($bRow);
        $this->assertTrue($bRow['overridden']);
        $this->assertSame('3', (string) $bRow['qty_display']);

        $a = $producers->firstWhere('id', $this->farmA->id);
        if ($a !== null) {
            $this->assertNull(collect($a['rows'])->firstWhere('product_id', $this->zucchini->id));
        }
    }

    public function test_move_endpoint_rejects_insufficient_target(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
                'target_product_id' => $this->zucchini->id,
                'target_producer_id' => $this->farmC->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('producer_position_overrides', 0);
    }

    public function test_farmer_export_follows_the_move(): void
    {
        $this->service()->move($this->zucchini->id, 0, self::DATE, $this->zucchini->id, $this->farmB->id, null);

        // Farm B's order now lists the zucchini; farm A's no longer does.
        $rowsB = $this->farmerRows($this->farmB->id);
        $this->assertcontainsProduct('Zucchini gruen', $rowsB);

        $rowsA = $this->farmerRows($this->farmA->id);
        $this->assertNotContainsProduct('Zucchini gruen', $rowsA);
    }

    public function test_reset_restores_default_attribution(): void
    {
        $admin = User::factory()->admin()->create();
        $this->service()->move($this->zucchini->id, 0, self::DATE, $this->zucchini->id, $this->farmB->id, null);

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move.reset'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
            ])
            ->assertOk();

        $this->assertDatabaseCount('producer_position_overrides', 0);
    }

    private function farmerRows(int $producerId): array
    {
        $orders = ShopOrder::query()->finalized()
            ->with(['items.product.producer.translations', 'items.product.translations', 'items.product.variants'])
            ->orderBy('expected_delivery_date')->orderBy('id')
            ->get();

        $method = new ReflectionMethod(AdminShopOrderController::class, 'buildFarmerOrderRows');

        return $method->invoke(app(AdminShopOrderController::class), $orders, $producerId);
    }

    private function assertContainsProduct(string $name, array $rows): void
    {
        $this->assertContains($name, array_column($rows, 'product_name'));
    }

    private function assertNotContainsProduct(string $name, array $rows): void
    {
        $this->assertNotContains($name, array_column($rows, 'product_name'));
    }
}
