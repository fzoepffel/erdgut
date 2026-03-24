<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProducerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_admin_producers_index(): void
    {
        $this->get(route('app.admin.producers.index'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden_on_admin_producers_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.producers.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_producer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.producers.store'), [
                'name' => 'Hof Sonnenfeld',
                'region' => 'Uckermark',
                'description' => 'Organic vegetables.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('producers', [
            'name' => 'Hof Sonnenfeld',
            'region' => 'Uckermark',
            'is_test' => false,
        ]);
    }

    public function test_admin_can_store_producer_with_uploaded_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('farm.jpg', 100, 100);

        $this->actingAs($admin)
            ->post(route('app.admin.producers.store'), [
                'name' => 'With Photo',
                'image' => $file,
            ])
            ->assertRedirect();

        $producer = Producer::query()->where('name', 'With Photo')->firstOrFail();
        $this->assertNotNull($producer->image_path);
        Storage::disk('public')->assertExists($producer->image_path);
    }

    public function test_admin_cannot_delete_producer_with_products(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();
        Product::factory()->create(['producer_id' => $producer->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.producers.index'))
            ->delete(route('app.admin.producers.destroy', $producer))
            ->assertRedirect(route('app.admin.producers.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('producers', ['id' => $producer->id]);
    }

    public function test_admin_can_delete_producer_without_products(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.producers.index'))
            ->delete(route('app.admin.producers.destroy', $producer))
            ->assertRedirect(route('app.admin.producers.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('producers', ['id' => $producer->id]);
    }

    public function test_non_admin_does_not_see_producers_nav_link(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee(route('app.admin.producers.index', absolute: false), false);
    }

    public function test_admin_producers_index_filters_by_name_or_region(): void
    {
        $admin = User::factory()->admin()->create();
        Producer::factory()->create(['name' => 'North Hill Farm', 'region' => 'Müritz']);
        Producer::factory()->create(['name' => 'South Fields', 'region' => 'Spreewald']);

        $this->actingAs($admin)
            ->get(route('app.admin.producers.index', ['q' => 'North Hill']))
            ->assertOk()
            ->assertSee('North Hill Farm', false)
            ->assertDontSee('South Fields', false);

        $this->actingAs($admin)
            ->get(route('app.admin.producers.index', ['q' => 'Spreewald']))
            ->assertOk()
            ->assertSee('South Fields', false);
    }

    public function test_admin_producers_index_rejects_oversized_search_query(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.producers.index'))
            ->get(route('app.admin.producers.index', ['q' => str_repeat('b', 121)]))
            ->assertRedirect(route('app.admin.producers.index'))
            ->assertSessionHasErrors('q');
    }
}
