<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the F3 (UX-review notes) producer typeahead — the create
 * and edit product forms expose a search-as-you-type combobox in
 * place of the older plain `<select>`. Hidden `producer_id` input
 * still ships with the form, so existing validation + persistence
 * is unchanged.
 */
class AdminProducerComboboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_f3_create_form_renders_combobox_and_hidden_id_input(): void
    {
        $admin = User::factory()->admin()->create();
        Producer::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('app.admin.products.create'))->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('product-form-producer-combobox', $html);
        $this->assertStringContainsString('id="producer_id_search"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        // The form still posts producer_id via a hidden input.
        $this->assertStringContainsString('name="producer_id"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        // The legacy plain <select> is gone.
        $this->assertStringNotContainsString('<select id="producer_id"', $html);
    }

    public function test_f3_edit_form_pre_fills_combobox_with_producer_name(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['producer_id' => $producer->id]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.products.edit', $product))
            ->assertOk();

        $html = $response->getContent();
        // Alpine x-data initialiser carries the localized name so the
        // input renders the producer's display label up-front (the
        // x-data syntax uses bare keys, not JSON, so we look for the
        // bare-key form: `selectedId: <id>`).
        $this->assertStringContainsString('selectedId: '.$producer->id, $html);
        $this->assertStringContainsString($producer->name, $html);
    }

    public function test_f3_create_form_still_validates_producer_id_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.products.create'))
            ->post(route('app.admin.products.store'), [
                // producer_id intentionally omitted; the typeahead's
                // hidden input is empty until a real option is picked.
                'category' => 'produce',
                'unit_price' => 1,
            ])
            ->assertSessionHasErrors('producer_id');
    }
}
