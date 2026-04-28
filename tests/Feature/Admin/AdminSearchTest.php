<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\Producer;
use App\Models\ProducerTranslation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get(route('app.admin.search'))->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->getJson(route('app.admin.search', ['q' => 'foo']))
            ->assertForbidden();
    }

    public function test_empty_query_returns_empty_groups(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('app.admin.search'))
            ->assertOk()
            ->assertExactJson([
                'users' => [],
                'subscriptions' => [],
                'deliveries' => [],
                'producers' => [],
                'products' => [],
            ]);
    }

    public function test_users_match_by_email_partial(): void
    {
        $admin = User::factory()->admin()->create();
        $needle = User::factory()->create(['email' => 'find-me@example.test']);
        User::factory()->create(['email' => 'someone-else@example.test']);

        $response = $this->actingAs($admin)
            ->getJson(route('app.admin.search', ['q' => 'find-me']))
            ->assertOk();

        $emails = collect($response->json('users'))->pluck('label')->all();
        $this->assertContains($needle->email, $emails);
        $this->assertNotContains('someone-else@example.test', $emails);
    }

    public function test_subscription_matches_by_numeric_id(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'sub-owner@example.test']);
        $sub = Subscription::factory()->create(['user_id' => $customer->id]);

        $response = $this->actingAs($admin)
            ->getJson(route('app.admin.search', ['q' => (string) $sub->id]))
            ->assertOk();

        $labels = collect($response->json('subscriptions'))->pluck('label')->all();
        $this->assertContains('#'.$sub->id, $labels);
    }

    public function test_delivery_matches_by_iso_date(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $delivery = Delivery::factory()->create([
            'user_id' => $customer->id,
            'scheduled_for_date' => '2026-05-04',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('app.admin.search', ['q' => '2026-05-04']))
            ->assertOk();

        $ids = collect($response->json('deliveries'))->pluck('id')->all();
        $this->assertContains($delivery->id, $ids);
    }

    public function test_producer_matches_by_translated_name(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        // Overwrite the auto-generated DE translation with a stable name we can grep for.
        ProducerTranslation::query()
            ->where('producer_id', $producer->id)
            ->where('locale', 'de')
            ->update(['name' => 'Hofgemeinschaft Findbar']);

        $response = $this->actingAs($admin)
            ->getJson(route('app.admin.search', ['q' => 'Findbar']))
            ->assertOk();

        $ids = collect($response->json('producers'))->pluck('id')->all();
        $this->assertContains($producer->id, $ids);
    }

    public function test_results_are_capped_at_five_per_group(): void
    {
        $admin = User::factory()->admin()->create();
        for ($i = 0; $i < 7; $i++) {
            User::factory()->create(['email' => "needle{$i}@example.test"]);
        }

        $response = $this->actingAs($admin)
            ->getJson(route('app.admin.search', ['q' => 'needle']))
            ->assertOk();

        $this->assertCount(5, $response->json('users'));
    }
}
