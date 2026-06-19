<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $user = User::factory()->create();

        $this->get(route('app.admin.users.show', $user))
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.users.show', $other))
            ->assertForbidden();
    }

    public function test_admin_can_open_detail_for_any_user(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'name' => 'Anna Schmitz',
            'email' => 'anna.schmitz@example.test',
            'phone' => '+49 30 1234567',
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.users.show', $customer))
            ->assertOk()
            ->assertSee('Anna Schmitz')
            ->assertSee('anna.schmitz@example.test')
            ->assertSee('+49 30 1234567');
    }

    public function test_subscription_section_surfaces_eggs_and_allergies(): void
    {
        $admin = User::factory()->admin()->create(['locale' => 'de']);
        $customer = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'preferences' => ['veg_only' => true, 'meat_add_on' => false, 'eggs_dairy_add_on' => true],
            'allergies' => ['gluten', 'nuts'],
            'allergies_note' => 'No mushrooms please',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.users.show', $customer))
            ->assertOk();

        $response->assertSee('Eier-Zusatz aktiv', false);
        $response->assertSee('Gluten');
        $response->assertSee('No mushrooms please');
    }

    public function test_addresses_section_renders_primary_first(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Primary',
            'recipient_name' => 'Anna Schmitz',
            'street_line_1' => 'Kastanienallee 12',
            'postal_code' => '10435',
            'city' => 'Berlin',
            'country_code' => 'DE',
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.users.show', $customer))
            ->assertOk()
            ->assertSee('Kastanienallee 12')
            ->assertSee('10435');
    }

    public function test_trial_orders_render_when_present(): void
    {
        $admin = User::factory()->admin()->create(['locale' => 'de']);
        $customer = User::factory()->create();

        Order::query()->create([
            'user_id' => $customer->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.users.show', $customer))
            ->assertOk()
            ->assertSee('Testbestellungen', false);
    }

    public function test_payments_section_includes_subscription_payments(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $customer->id]);

        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'amount' => 24.50,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.users.show', $customer))
            ->assertOk()
            ->assertSee('24.50');
    }
}
