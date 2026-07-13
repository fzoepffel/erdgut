<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks the producer picker on the product form: an Alpine combobox
 * (F3) that since the multi-producer epic collects SEVERAL farms as
 * ordered chips. Chip order is pivot order; the first chip is the
 * primary farm. The form posts `producer_ids[]`; the legacy single
 * `producer_id` field keeps working via the FormRequest mapping.
 */
class AdminProducerComboboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_renders_chips_combobox_without_legacy_input(): void
    {
        $admin = User::factory()->admin()->create();
        Producer::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('app.admin.products.create'))->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('product-form-producer-chips', $html);
        $this->assertStringContainsString('product-form-producer-combobox', $html);
        $this->assertStringContainsString('id="producer_ids_search"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('name="producer_ids[]"', $html);
        $this->assertStringContainsString('producer-chip-primary-badge', $html);
        // The single-producer contract is gone from the markup.
        $this->assertStringNotContainsString('name="producer_id"', $html);
        $this->assertStringNotContainsString('<select id="producer_id"', $html);
    }

    public function test_edit_form_prefills_chips_in_pivot_order(): void
    {
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $farmA->id]);
        $product->syncProducers([$farmB->id, $farmA->id]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        // The Alpine initializer carries the chips in pivot order: farm B
        // (position 0, primary) before farm A.
        $this->assertMatchesRegularExpression(
            '/selected: \[\{"id":'.$farmB->id.',.*?\},\{"id":'.$farmA->id.',/s',
            $html,
        );
    }

    public function test_edit_form_falls_back_to_the_legacy_single_column(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);
        // Simulate a pre-backfill product: no pivot rows at all.
        DB::table('product_producer')->where('product_id', $product->id)->delete();

        $html = $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('selected: [{"id":'.$producer->id.',', $html);
    }

    public function test_create_validates_producer_ids_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.create'))
            ->post(route('app.admin.products.store'), [
                // No farms picked: the chip list is empty, so no
                // producer_ids[] inputs ship with the form.
                'category' => 'produce',
                'unit_price' => 1,
            ])
            ->assertSessionHasErrors('producer_ids');
    }
}
