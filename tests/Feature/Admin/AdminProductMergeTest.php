<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\User;
use App\Services\Admin\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Admin product merge (P6): danger-zone card on the loser's edit page,
 * confirmation screen with counts, destructive POST, guards.
 */
class AdminProductMergeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'producer_id' => Producer::factory()->create()->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
        ]);
    }

    public function test_edit_page_shows_the_merge_card(): void
    {
        $loser = $this->product();
        $this->product(); // at least one possible target

        $html = $this->actingAs($this->admin())
            ->get(route('app.admin.products.edit', $loser))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('product-merge-card', $html);
        $this->assertStringContainsString('product-merge-combobox', $html);
        $this->assertStringContainsString(route('app.admin.products.merge.confirm', $loser), $html);
    }

    public function test_confirm_page_shows_the_counts(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        ProductStockInflow::create([
            'product_id' => $loser->id, 'producer_id' => $loser->producer_id,
            'received_on' => '2026-07-20', 'quantity' => 10, 'freshness_window_days' => 2,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.products.merge.confirm', ['product' => $loser, 'winner_id' => $winner->id]))
            ->assertOk();

        $response->assertSee('merge-moves', false);
        $response->assertSee(__('admin.products.merge.moves_inflows'));
        $response->assertSee($winner->name);
        $response->assertSee($loser->producer->name); // farm gets appended
        $response->assertSee(route('app.admin.products.merge.execute', $loser), false);
    }

    public function test_execute_merges_and_redirects_to_the_winner(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00:00'));
        $winner = $this->product();
        $loser = $this->product();
        ProductStockInflow::create([
            'product_id' => $loser->id, 'producer_id' => $loser->producer_id,
            'received_on' => '2026-07-16', 'quantity' => 10, 'freshness_window_days' => 2,
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.products.merge.execute', $loser), ['winner_id' => $winner->id])
            ->assertRedirect(route('app.admin.products.edit', $winner));

        $this->assertDatabaseMissing('products', ['id' => $loser->id]);
        // The winner's availability now includes the loser's batch.
        $available = app(InventoryService::class)->availableOnDate($winner, Carbon::parse('2026-07-16'));
        $this->assertEqualsWithDelta(10.0, $available, 0.001);
        Carbon::setTestNow();
    }

    public function test_validation_and_guards(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $admin = $this->admin();

        // Missing winner.
        $this->actingAs($admin)
            ->post(route('app.admin.products.merge.execute', $loser), [])
            ->assertSessionHasErrors('winner_id');

        // Unknown winner.
        $this->actingAs($admin)
            ->post(route('app.admin.products.merge.execute', $loser), ['winner_id' => 999999])
            ->assertSessionHasErrors('winner_id');

        // Self merge.
        $this->actingAs($admin)
            ->post(route('app.admin.products.merge.execute', $loser), ['winner_id' => $loser->id])
            ->assertRedirect(route('app.admin.products.edit', $loser))
            ->assertSessionHas('error', __('admin.products.merge.error_self'));

        // Protected loser.
        config()->set('spargel.white_product_id', $loser->id);
        $this->actingAs($admin)
            ->post(route('app.admin.products.merge.execute', $loser), ['winner_id' => $winner->id])
            ->assertRedirect(route('app.admin.products.edit', $loser))
            ->assertSessionHas('error', __('admin.products.merge.error_protected'));
        config()->set('spargel.white_product_id', null);

        $this->assertDatabaseHas('products', ['id' => $loser->id]);
    }

    public function test_non_admins_are_forbidden(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.products.merge.confirm', ['product' => $loser, 'winner_id' => $winner->id]))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('app.admin.products.merge.execute', $loser), ['winner_id' => $winner->id])
            ->assertForbidden();
    }
}
