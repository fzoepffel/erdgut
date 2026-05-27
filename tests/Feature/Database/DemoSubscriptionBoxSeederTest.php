<?php

namespace Tests\Feature\Database;

use App\Enums\BoxStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSubscriptionBoxSeeder;
use Database\Seeders\ProducerSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSubscriptionBoxSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_subscriptions_and_boxes(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(DemoSubscriptionBoxSeeder::class);

        $this->assertGreaterThanOrEqual(2, Subscription::query()->where('is_test', true)->count());
        $this->assertGreaterThanOrEqual(6, Box::query()->where('is_test', true)->count());
    }

    public function test_demo_boxes_cover_editable_locked_delivered_statuses(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(DemoSubscriptionBoxSeeder::class);

        $editable = Box::query()->where('is_test', true)->where('status', BoxStatus::Editable)->count();
        $locked = Box::query()->where('is_test', true)->where('status', BoxStatus::Locked)->count();
        $delivered = Box::query()->where('is_test', true)->where('status', BoxStatus::Delivered)->count();

        $this->assertGreaterThanOrEqual(1, $editable);
        $this->assertGreaterThanOrEqual(1, $locked);
        $this->assertGreaterThanOrEqual(1, $delivered);
    }

    public function test_demo_subscriptions_and_boxes_are_marked_as_test(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(DemoSubscriptionBoxSeeder::class);

        $this->assertGreaterThanOrEqual(2, User::query()->where('is_test', true)->count());
        $this->assertSame(0, User::query()->where('is_test', false)->count());
        $this->assertGreaterThanOrEqual(2, Address::query()->where('is_test', true)->count());
        $this->assertSame(0, Address::query()->where('is_test', false)->count());
        $this->assertSame(0, Subscription::query()->where('is_test', false)->count());
        $this->assertSame(0, Box::query()->where('is_test', false)->count());
        $this->assertSame(0, BoxItem::query()->where('is_test', false)->count());
        $this->assertGreaterThanOrEqual(4, Delivery::query()->where('is_test', true)->count());
        $this->assertSame(0, Delivery::query()->where('is_test', false)->count());
    }

    public function test_demo_seeder_creates_deliveries_and_addresses(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(DemoSubscriptionBoxSeeder::class);

        $this->assertGreaterThanOrEqual(2, Address::query()->where('is_test', true)->count());
        $this->assertGreaterThanOrEqual(6, Delivery::query()->where('is_test', true)->count());
    }

    public function test_dashboard_shows_content_for_demo_users_after_seeding(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(DemoSubscriptionBoxSeeder::class);

        $user = User::query()->where('email', 'demo-qa@erdgut.local')->first();
        $this->assertNotNull($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Berlin')
            // The dashboard hero's primary CTA on a subscribed user with a
            // current box. Replaced the older at-a-glance "View box" /
            // "View current box" links when the editorial dashboard
            // (ERD-351) landed.
            ->assertSee(__('customer.dashboard.hero.action.view_box'));
    }

    public function test_demo_boxes_have_box_items(): void
    {
        $this->seed(ProducerSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(DemoSubscriptionBoxSeeder::class);

        $boxes = Box::query()->where('is_test', true)->with('items')->get();
        foreach ($boxes as $box) {
            $this->assertGreaterThan(0, $box->items->count(), "Box {$box->id} should have at least one item");
        }
    }

    public function test_non_seeded_subscriptions_and_boxes_can_be_created_as_real_data(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $box = Box::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $this->assertFalse($user->is_test);
        $this->assertFalse($subscription->is_test);
        $this->assertFalse($box->is_test);
        $this->assertFalse($address->is_test);
        $this->assertFalse($delivery->is_test);
    }
}
