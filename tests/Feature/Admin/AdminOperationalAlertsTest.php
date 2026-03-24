<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationalAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_all_clear_when_no_operational_issues(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="admin-operational-alerts-clear"', false);
    }

    public function test_dashboard_shows_draft_product_alert_with_deep_link_to_filtered_products(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        Product::factory()->for($producer)->create(['is_published' => false]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.dashboard'))
            ->assertOk();

        $response->assertDontSee('data-testid="admin-operational-alerts-clear"', false);
        $response->assertSee(route('app.admin.products.index', ['published' => '0'], false), false);
    }
}
