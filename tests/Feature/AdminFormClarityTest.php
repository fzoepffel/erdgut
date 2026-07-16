<?php

namespace Tests\Feature;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the admin-form-clarity batch (E3, E4, E5, F5, F6).
 */
class AdminFormClarityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ---------------------------------------------------------------------
    // E3 — multi-language tabs badge missing required fields live.
    // ---------------------------------------------------------------------

    public function test_e3_producer_form_initial_render_marks_all_tabs_missing(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.producers.create'))
            ->assertOk();

        // All three tabs must surface the live "missing fields" dot on
        // a fresh form so the admin sees DE/EN/FR are required up-front,
        // not only after a failed submit.
        $response->assertSee('producer-form-tab-de', false);
        $response->assertSee('producer-form-tab-missing-de', false);
        $response->assertSee('producer-form-tab-missing-en', false);
        $response->assertSee('producer-form-tab-missing-fr', false);
    }

    public function test_e3_producer_form_with_filled_de_data_does_not_mark_de_missing_on_edit(): void
    {
        $admin = $this->admin();
        $producer = Producer::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.producers.edit', $producer))
            ->assertOk();

        // Edit form has all three locales filled by the factory's afterCreating
        // hook, so initial-state Alpine map should have no missing tabs. The
        // `x-show` directive renders the dot conditionally — we assert that the
        // pre-computed `tabMissing` map for DE is `false` (in JSON: ":false").
        $response->assertSee('"de":false', false);
        $response->assertSee('"en":false', false);
        $response->assertSee('"fr":false', false);
    }

    public function test_e3_product_form_emits_per_locale_tab_state(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.create'))
            ->assertOk();

        // Product form follows the same pattern.
        $response->assertSee('product-form-tab-de', false);
        $response->assertSee('product-form-tab-missing-de', false);
        $response->assertSee('product-form-tab-missing-en', false);
        $response->assertSee('product-form-tab-missing-fr', false);
        $response->assertSee('recheckTabs', false);
    }

    // ---------------------------------------------------------------------
    // E4 — producer list highlights producers with zero products.
    // ---------------------------------------------------------------------

    public function test_e4_producer_with_zero_products_shows_add_product_hint(): void
    {
        $admin = $this->admin();
        Producer::factory()->create(); // no products

        $response = $this->actingAs($admin)
            ->get(route('app.admin.producers.index'))
            ->assertOk();

        $response->assertSee('admin-producer-no-products', false);
        $response->assertSee(__('Add product'));
    }

    public function test_e4_producer_with_products_does_not_show_zero_hint(): void
    {
        $admin = $this->admin();
        $producer = Producer::factory()->create();
        Product::factory()->create(['producer_id' => $producer->id]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.producers.index'))
            ->assertOk();

        $response->assertDontSee('admin-producer-no-products', false);
    }

    public function test_e4_create_product_with_query_string_preselects_producer(): void
    {
        $admin = $this->admin();
        $producer = Producer::factory()->create();
        // Fixed name: faker company names can carry apostrophes, which the
        // Alpine initialiser JSON-escapes (JSON_HEX_APOS → \u0027) and a
        // raw assertSee then misses. This was a recurring CI flake.
        $producer->translations()->update(['name' => 'Hof Sonnenacker']);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.create', ['producer_id' => $producer->id]))
            ->assertOk();

        // The producer picker is a multi-farm chips combobox since P5;
        // the preselect arrives as the first (primary) chip in the
        // Alpine initialiser.
        $response->assertSee('selected: [{"id":'.$producer->id.',', false);
        $response->assertSee('Hof Sonnenacker', false);
    }

    // ---------------------------------------------------------------------
    // E5 — destructive "Delete" action is in the same visual register as Edit.
    // ---------------------------------------------------------------------

    public function test_e5_producer_index_delete_uses_quiet_text_link(): void
    {
        $admin = $this->admin();
        Producer::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.producers.index'))
            ->assertOk();

        $response->assertSee('admin-producer-delete-trigger', false);
        // No btn-danger-sm class on the producer-row delete trigger anymore.
        $html = $response->getContent();
        $this->assertStringNotContainsString(
            'class="btn-danger-sm"',
            // strip HEAD-side btn-danger-sm references in @vite output if any
            // by matching only inside the producers-table area:
            substr($html, (int) strpos($html, 'admin-producer-delete-trigger') - 200,
                400),
            'Delete trigger near the producers table must not be a btn-danger-sm.'
        );
    }

    public function test_e5_product_index_delete_uses_quiet_text_link(): void
    {
        $admin = $this->admin();
        Product::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.index'))
            ->assertOk();

        $response->assertSee('admin-product-delete-trigger', false);
    }

    // ---------------------------------------------------------------------
    // F5 — visibility explainer is rendered on the product form.
    // ---------------------------------------------------------------------

    public function test_f5_product_form_renders_visibility_explainer(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.create'))
            ->assertOk();

        $response->assertSee('product-visibility-explainer', false);
        $response->assertSee(__('admin.products.visibility_explainer_title'));
        $response->assertSee(__('admin.products.visibility_outcome_visible'));
        $response->assertSee(__('admin.products.visibility_outcome_hidden_unavailable'));
        $response->assertSee(__('admin.products.visibility_outcome_draft'));
    }

    // ---------------------------------------------------------------------
    // F6 — product list shows margin (and "—" when buying price missing).
    // ---------------------------------------------------------------------

    public function test_f6_product_list_includes_margin_column(): void
    {
        $admin = $this->admin();
        // Seed one product so the table renders (the empty-state branch
        // bypasses the table head where Margin lives).
        Product::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.index'))
            ->assertOk();

        $response->assertSee('admin-products-col-margin', false);
        $response->assertSee(__('Margin'));
    }

    public function test_f6_product_with_buying_price_renders_margin_percent(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'unit_price' => 10.00,
            'buying_price_cents' => 700, // EUR 7.00 → 30% margin
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.index'))
            ->assertOk();

        // Margin = (10 - 7) / 10 * 100 = 30.0 %
        $response->assertSee('30,0', false);
    }

    public function test_f6_product_without_buying_price_shows_em_dash(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'unit_price' => 10.00,
            'buying_price_cents' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.index'))
            ->assertOk();

        $html = $response->getContent();
        // Confirm the em-dash placeholder is present in the margin cell —
        // we check for the per-row span shape used by the template.
        $this->assertStringContainsString('text-gray-400">—', $html);
    }

    public function test_f6_product_with_low_margin_uses_warning_color(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'unit_price' => 10.00,
            'buying_price_cents' => 950, // 5% margin → red
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.index'))
            ->assertOk();

        $response->assertSee('text-rose-700', false);
    }

    public function test_f6_gross_margin_percent_returns_null_when_buying_price_unset(): void
    {
        $product = new Product([
            'unit_price' => 10.00,
            'buying_price_cents' => null,
        ]);
        $this->assertNull($product->grossMarginPercent());
    }

    public function test_f6_gross_margin_percent_returns_null_when_unit_price_zero(): void
    {
        $product = new Product([
            'unit_price' => 0.00,
            'buying_price_cents' => 100,
        ]);
        $this->assertNull($product->grossMarginPercent());
    }

    public function test_f6_gross_margin_percent_computes_correctly(): void
    {
        $product = new Product([
            'unit_price' => 10.00,
            'buying_price_cents' => 700,
        ]);
        $this->assertSame(30.0, $product->grossMarginPercent());
    }

    public function test_f6_gross_margin_uses_display_unit_not_canonical(): void
    {
        // Regression: a mass product priced 19.50 €/kg stores unit_price
        // canonically as 0.0195 €/g, while the buying price 13.00 €/kg is
        // stored verbatim (1300 cents). The margin must compare both in the
        // display unit (€/kg), not subtract €13.00 from €0.0195.
        $product = new Product([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.0195,
            'buying_price_cents' => 1300,
        ]);

        $this->assertSame(6.50, $product->grossMargin());
        $this->assertSame(33.3, $product->grossMarginPercent());
    }
}
