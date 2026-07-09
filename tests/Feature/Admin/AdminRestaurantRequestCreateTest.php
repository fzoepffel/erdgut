<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderKind;
use App\Enums\RestaurantOrderStatus;
use App\Models\AuditEvent;
use App\Models\Producer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Admin-entered B2B requests on behalf of a restaurant ("Anfrage erfassen"). */
class AdminRestaurantRequestCreateTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Producer::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                ['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10],
                ['name' => 'Wildkräuter', 'unit' => 'kg', 'quantity' => 2],
            ],
            'expected_delivery_date' => Carbon::tomorrow()->toDateString(),
            'delivery_street' => 'Marktstr. 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
        ], $overrides);
    }

    public function test_access_control(): void
    {
        $this->get(route('app.admin.restaurant-orders.create'))->assertRedirect(route('auth.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.restaurant-orders.create'))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post(route('app.admin.restaurant-orders.store'), $this->payload())->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('app.admin.restaurant-orders.create'))
            ->assertOk()
            ->assertSee('data-testid="admin-request-account-select"', false);
    }

    public function test_admin_creates_request_with_catalog_and_free_form_lines_without_mail(): void
    {
        Mail::fake();
        $profile = RestaurantProfile::factory()->verified()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(
            route('app.admin.restaurant-orders.store'),
            $this->payload(['restaurant_user_id' => $profile->user_id]),
        );

        $order = RestaurantOrder::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('app.admin.restaurant-orders.show', $order));

        $this->assertSame(RestaurantOrderKind::Request, $order->kind);
        $this->assertSame(RestaurantOrderStatus::Submitted, $order->status);
        $this->assertSame($profile->user_id, $order->user_id);

        $items = $order->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame($this->product->id, $items[0]->product_id);
        $this->assertNull($items[1]->product_id);
        $this->assertSame('Wildkräuter', $items[1]->name_snapshot);

        // It shows up in the normal Anfragen queue.
        $this->actingAs($admin)->get(route('app.admin.restaurant-orders.index'))
            ->assertOk()
            ->assertSee(route('app.admin.restaurant-orders.show', $order), false);

        $event = AuditEvent::query()
            ->where('entity_type', 'restaurant_order')->where('entity_id', $order->id)
            ->where('action', 'create_request')->first();
        $this->assertNotNull($event);
        $this->assertSame('admin', $event->metadata['channel']);

        // Silent creation: the restaurant gets no mail, and the admin does not notify themselves.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_weekly_line_creates_pending_standing_order_that_activates_on_confirm(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 8)); // Wednesday
        try {
            Mail::fake();
            $profile = RestaurantProfile::factory()->verified()->create();
            $admin = User::factory()->admin()->create();

            $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
                'restaurant_user_id' => $profile->user_id,
                'items' => [
                    ['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 5, 'recurrence' => 'weekly', 'until' => '2026-07-30'],
                ],
            ]))->assertRedirect();

            $order = RestaurantOrder::query()->latest('id')->firstOrFail();
            $standing = RestaurantStandingOrder::query()->firstOrFail();
            $this->assertTrue($standing->isPending());
            $this->assertSame($standing->id, $order->source_standing_order_id);
            // Nothing materialized while the request is still pending review.
            $this->assertSame(1, RestaurantOrder::query()->count());

            // Confirming the request in the queue activates the recurrence.
            $this->actingAs($admin)
                ->post(route('app.admin.restaurant-orders.confirm', $order))
                ->assertRedirect();

            $this->assertTrue($standing->refresh()->isActive());
            $materialized = RestaurantOrder::query()
                ->where('source_standing_order_id', $standing->id)
                ->where('id', '!=', $order->id)
                ->get();
            $this->assertNotEmpty($materialized);
            foreach ($materialized as $week) {
                $this->assertTrue($week->expected_delivery_date->gt($order->expected_delivery_date));
            }
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_auto_creates_verified_account_and_reuses_existing_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'name' => 'Trattoria Vera', 'email' => 'Vera@Example.com',
        ]))->assertRedirect();

        $user = User::query()->where('email', 'vera@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->restaurantProfile->isVerified());
        $this->assertSame($user->id, RestaurantOrder::query()->latest('id')->firstOrFail()->user_id);

        // A second request with the same email reuses the account.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'name' => 'Trattoria Vera', 'email' => 'vera@example.com',
        ]))->assertRedirect();
        $this->assertSame(1, User::query()->where('email', 'vera@example.com')->count());

        Mail::assertNothingSent();
    }

    public function test_validation_rejects_bad_payloads(): void
    {
        $admin = User::factory()->admin()->create();
        $verified = RestaurantProfile::factory()->verified()->create();

        // Neither an account nor name+email.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload())
            ->assertSessionHasErrors('restaurant_user_id');

        // An unverified account.
        $unverified = RestaurantProfile::factory()->create();
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'restaurant_user_id' => $unverified->user_id,
        ]))->assertSessionHasErrors('restaurant_user_id');

        // A line with neither product nor name.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'restaurant_user_id' => $verified->user_id,
            'items' => [['quantity' => 3]],
        ]))->assertSessionHasErrors('items.0.name');

        // A free-form line without a unit.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'restaurant_user_id' => $verified->user_id,
            'items' => [['name' => 'Artischocken', 'quantity' => 3]],
        ]))->assertSessionHasErrors('items.0.unit');

        // An unpublished product would be silently dropped by the service; rejected here instead.
        $hidden = Product::factory()->create(['is_available' => true, 'is_published' => false]);
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'restaurant_user_id' => $verified->user_id,
            'items' => [['product_id' => $hidden->id, 'quantity' => 3]],
        ]))->assertSessionHasErrors('items.0.product_id');

        // A weekly end date before the delivery date.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'restaurant_user_id' => $verified->user_id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 3, 'recurrence' => 'weekly', 'until' => Carbon::yesterday()->toDateString()]],
        ]))->assertSessionHasErrors('items.0.until');

        // A delivery date in the past.
        $this->actingAs($admin)->post(route('app.admin.restaurant-orders.store'), $this->payload([
            'restaurant_user_id' => $verified->user_id,
            'expected_delivery_date' => Carbon::yesterday()->toDateString(),
        ]))->assertSessionHasErrors('expected_delivery_date');

        $this->assertSame(0, RestaurantOrder::query()->count());
    }
}
