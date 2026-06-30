<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopClosedDay;
use App\Models\ShopClosureRule;
use App\Models\ShopForcedOpenDay;
use App\Models\User;
use App\Services\Shop\ShopClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Recurring auto-closure rules: a delivery weekday closes automatically
 * lead_days before, at cutoff_time. Manual overrides always win.
 */
class ShopClosureRuleTest extends TestCase
{
    use RefreshDatabase;

    private const WED = '2026-06-03';   // Wednesday — governed by the rule

    private const THU = '2026-06-04';   // Thursday — never governed (control)

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 09:00, before the Mon-18:00 cutoff for the 2026-06-03 Wednesday.
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        foreach ([self::WED, self::THU] as $date) {
            ProductStockInflow::create([
                'product_id' => $this->product->id,
                'received_on' => $date,
                'quantity' => 10,
                'freshness_window_days' => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeWednesdayRule(bool $active = true): ShopClosureRule
    {
        return ShopClosureRule::query()->create([
            'weekday' => 3, // Carbon dayOfWeek Wednesday
            'lead_days' => 2,
            'cutoff_time' => '18:00:00',
            'is_active' => $active,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    private function service(): ShopClosureService
    {
        return app(ShopClosureService::class);
    }

    public function test_sanity_dates_are_the_expected_weekdays(): void
    {
        $this->assertTrue(Carbon::parse(self::WED)->isWednesday());
        $this->assertTrue(Carbon::parse(self::THU)->isThursday());
    }

    public function test_rule_day_is_open_before_cutoff_and_closed_after(): void
    {
        $this->makeWednesdayRule();

        // Monday 09:00 — before the Monday 18:00 cutoff.
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::THU)));

        // Monday 18:30 — cutoff passed.
        Carbon::setTestNow(Carbon::parse('2026-06-01 18:30:00'));
        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::THU)));
    }

    public function test_inactive_rule_never_closes(): void
    {
        $this->makeWednesdayRule(active: false);
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00'));

        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_manual_close_beats_rule_before_cutoff(): void
    {
        $this->makeWednesdayRule();
        ShopClosedDay::query()->create(['closed_on' => self::WED, 'created_by_user_id' => $this->admin->id]);

        // Even before the cutoff, the manual close wins.
        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_forced_open_beats_rule_after_cutoff(): void
    {
        $this->makeWednesdayRule();
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00')); // past cutoff

        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));

        ShopForcedOpenDay::query()->create(['forced_open_on' => self::WED, 'created_by_user_id' => $this->admin->id]);
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_toggle_after_cutoff_cycles_forced_open_then_manual_closed_then_reset(): void
    {
        $this->makeWednesdayRule();
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00')); // rule-closed

        // rule_closed → click → forced-open (override beats rule)
        $this->assertFalse($this->service()->toggleDay(Carbon::parse(self::WED), $this->admin->id));
        $this->assertDatabaseHas('shop_forced_open_days', ['forced_open_on' => self::WED]);
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));

        // forced-open → click → manual closed
        $this->assertTrue($this->service()->toggleDay(Carbon::parse(self::WED), $this->admin->id));
        $this->assertDatabaseMissing('shop_forced_open_days', ['forced_open_on' => self::WED]);
        $this->assertDatabaseHas('shop_closed_days', ['closed_on' => self::WED]);

        // reset → follows the rule again (closed, post-cutoff) with no override rows
        $this->service()->clearOverride(Carbon::parse(self::WED));
        $this->assertDatabaseMissing('shop_closed_days', ['closed_on' => self::WED]);
        $this->assertDatabaseMissing('shop_forced_open_days', ['forced_open_on' => self::WED]);
        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_toggle_before_cutoff_does_not_pin_force_open(): void
    {
        $this->makeWednesdayRule();
        // Monday 09:00 — rule pending (still open).

        // open → click → manual closed
        $this->assertTrue($this->service()->toggleDay(Carbon::parse(self::WED), $this->admin->id));
        $this->assertDatabaseHas('shop_closed_days', ['closed_on' => self::WED]);

        // closed → click → open; since the rule would NOT close it yet, no
        // force-open pin is needed — it just follows the (pending) rule.
        $this->assertFalse($this->service()->toggleDay(Carbon::parse(self::WED), $this->admin->id));
        $this->assertDatabaseMissing('shop_closed_days', ['closed_on' => self::WED]);
        $this->assertDatabaseMissing('shop_forced_open_days', ['forced_open_on' => self::WED]);
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_states_for_range_reports_pending_then_closed(): void
    {
        $this->makeWednesdayRule();
        $from = Carbon::parse(self::WED);
        $to = Carbon::parse(self::WED);

        $pending = $this->service()->statesForRange($from, $to)[self::WED];
        $this->assertSame('rule_pending', $pending['state']);
        $this->assertTrue($pending['has_rule']);
        $this->assertNotNull($pending['cutoff_label']);

        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00'));
        $closed = $this->service()->statesForRange($from, $to)[self::WED];
        $this->assertSame('rule_closed', $closed['state']);
        $this->assertTrue($closed['closed']);
    }

    public function test_storefront_date_picker_reflects_rule(): void
    {
        $this->makeWednesdayRule();

        // Before cutoff: Wednesday is still offered.
        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertSee('value="'.self::WED.'"', false);

        // After cutoff: Wednesday drops out, Thursday stays.
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00'));
        $response = $this->get(route('public.shop.index'))->assertOk();
        $response->assertDontSee('value="'.self::WED.'"', false);
        $response->assertSee('value="'.self::THU.'"', false);
    }

    public function test_store_rejects_rule_closed_date_after_cutoff(): void
    {
        $this->makeWednesdayRule();
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00'));

        $this->post(route('public.shop.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::WED,
            'quantities' => [$this->product->id => 1],
            'consent' => '1',
        ])->assertSessionHasErrors('expected_delivery_date');
    }

    public function test_rule_crud_endpoints(): void
    {
        // Create
        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.rules.store'), [
                'weekday' => 3,
                'lead_days' => 2,
                'cutoff_time' => '18:00',
                'month' => '2026-06',
            ])
            ->assertOk()
            ->assertJsonPath('rules.0.weekday', 3)
            ->assertJsonPath('rules.0.lead_days', 2);

        $this->assertDatabaseHas('shop_closure_rules', ['weekday' => 3, 'lead_days' => 2]);

        // Update (same weekday upserts)
        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.rules.store'), [
                'weekday' => 3,
                'lead_days' => 4,
                'cutoff_time' => '12:00',
            ])
            ->assertOk();
        $this->assertSame(1, ShopClosureRule::query()->where('weekday', 3)->count());
        $this->assertDatabaseHas('shop_closure_rules', ['weekday' => 3, 'lead_days' => 4]);

        // Delete
        $rule = ShopClosureRule::query()->where('weekday', 3)->first();
        $this->actingAs($this->admin)
            ->deleteJson(route('app.admin.shop-closures.rules.destroy', ['rule' => $rule->id]))
            ->assertOk();
        $this->assertDatabaseMissing('shop_closure_rules', ['id' => $rule->id]);
    }

    public function test_reset_endpoint_clears_override(): void
    {
        $this->makeWednesdayRule();
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00'));
        ShopForcedOpenDay::query()->create(['forced_open_on' => self::WED, 'created_by_user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.reset'), ['date' => self::WED, 'month' => '2026-06'])
            ->assertOk();

        $this->assertDatabaseMissing('shop_forced_open_days', ['forced_open_on' => self::WED]);
        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_per_day_closing_time_overrides_rule_to_close_earlier(): void
    {
        $this->makeWednesdayRule(); // rule cutoff = Monday 18:00
        // One-off cutoff Monday 10:00 — earlier than the rule.
        $this->service()->setClosingTime(Carbon::parse(self::WED), Carbon::parse('2026-06-01 10:00:00'), $this->admin->id);

        // now Monday 09:00 → before the one-off cutoff → open
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));

        // Monday 11:00 → past the one-off cutoff, even though the rule's 18:00 hasn't passed
        Carbon::setTestNow(Carbon::parse('2026-06-01 11:00:00'));
        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_per_day_closing_time_overrides_rule_to_stay_open_later(): void
    {
        $this->makeWednesdayRule(); // rule cutoff = Monday 18:00
        // One-off cutoff Tuesday 12:00 — later than the rule.
        $this->service()->setClosingTime(Carbon::parse(self::WED), Carbon::parse('2026-06-02 12:00:00'), $this->admin->id);

        // Monday 19:00 — the rule would have closed it, but the one-off keeps it open.
        Carbon::setTestNow(Carbon::parse('2026-06-01 19:00:00'));
        $this->assertFalse($this->service()->isClosed(Carbon::parse(self::WED)));

        // Tuesday 13:00 — past the one-off cutoff → closed.
        Carbon::setTestNow(Carbon::parse('2026-06-02 13:00:00'));
        $this->assertTrue($this->service()->isClosed(Carbon::parse(self::WED)));
    }

    public function test_states_for_range_reports_manual_scheduled_then_closed(): void
    {
        $this->makeWednesdayRule();
        $this->service()->setClosingTime(Carbon::parse(self::WED), Carbon::parse('2026-06-02 12:00:00'), $this->admin->id);

        $scheduled = $this->service()->statesForRange(Carbon::parse(self::WED), Carbon::parse(self::WED))[self::WED];
        $this->assertSame('manual_scheduled', $scheduled['state']);
        $this->assertFalse($scheduled['closed']);
        $this->assertTrue($scheduled['has_override']);
        $this->assertSame('2026-06-02T12:00', $scheduled['closes_at']);
        $this->assertNotNull($scheduled['cutoff_label']);

        Carbon::setTestNow(Carbon::parse('2026-06-02 13:00:00'));
        $closed = $this->service()->statesForRange(Carbon::parse(self::WED), Carbon::parse(self::WED))[self::WED];
        $this->assertSame('manual_closed', $closed['state']);
        $this->assertTrue($closed['closed']);
    }

    public function test_set_day_endpoint_actions(): void
    {
        $this->makeWednesdayRule();

        // closes_at → one-off cutoff stored
        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.set-day'), [
                'date' => self::WED, 'action' => 'closes_at', 'closes_at' => '2026-06-02T12:00', 'month' => '2026-06',
            ])->assertOk();
        $row = ShopClosedDay::query()->whereDate('closed_on', self::WED)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->closes_at);

        // keep_open → forced-open, no closed row
        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.set-day'), ['date' => self::WED, 'action' => 'keep_open'])
            ->assertOk();
        $this->assertDatabaseHas('shop_forced_open_days', ['forced_open_on' => self::WED]);
        $this->assertDatabaseMissing('shop_closed_days', ['closed_on' => self::WED]);

        // close_now → closed row with null closes_at, no forced-open
        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.set-day'), ['date' => self::WED, 'action' => 'close_now'])
            ->assertOk();
        $this->assertDatabaseHas('shop_closed_days', ['closed_on' => self::WED, 'closes_at' => null]);
        $this->assertDatabaseMissing('shop_forced_open_days', ['forced_open_on' => self::WED]);

        // reset → follows the rule again
        $this->actingAs($this->admin)
            ->postJson(route('app.admin.shop-closures.set-day'), ['date' => self::WED, 'action' => 'reset'])
            ->assertOk();
        $this->assertDatabaseMissing('shop_closed_days', ['closed_on' => self::WED]);
    }

    public function test_purge_removes_past_forced_open_days(): void
    {
        ShopForcedOpenDay::query()->create(['forced_open_on' => '2026-05-20', 'created_by_user_id' => $this->admin->id]);
        ShopForcedOpenDay::query()->create(['forced_open_on' => self::WED, 'created_by_user_id' => $this->admin->id]);

        $this->service()->purgeExpired();

        $this->assertDatabaseMissing('shop_forced_open_days', ['forced_open_on' => '2026-05-20']);
        $this->assertDatabaseHas('shop_forced_open_days', ['forced_open_on' => self::WED]);
    }
}
