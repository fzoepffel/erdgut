<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\ChargeSubscriptions;
use App\Enums\BillingMode;
use App\Enums\SubscriptionStatus;
use App\Enums\Timeslot;
use App\Models\AuditEvent;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_subscription_create(): void
    {
        $this->get(route('app.admin.subscriptions.create'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_cannot_access_admin_subscription_create(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.subscriptions.create'))
            ->assertForbidden();
    }

    public function test_admin_can_render_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.subscriptions.create'))
            ->assertOk()
            ->assertSee('name="billing_mode"', false)
            ->assertSee(BillingMode::ManualInvoice->label())
            ->assertSee(BillingMode::ManualCash->label());
    }

    public function test_admin_can_create_manual_invoice_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('app.admin.subscriptions.store'), [
                'user_id' => $customer->id,
                'billing_mode' => BillingMode::ManualInvoice->value,
                'plan' => 'weekly',
                'box_size' => 'medium',
                'fulfillment_mode' => 'delivery',
                // D2 (UX notes): zone is now pinned to Wed × Afternoon.
                'delivery_weekday' => 3,
                'preferred_timeslot' => Timeslot::Afternoon->value,
                'preferences' => [],
                'reason' => 'Phone sign-up; invoiced monthly.',
            ])
            ->assertRedirect(route('app.admin.subscriptions.index'))
            ->assertSessionHas('status', 'admin-subscription-created');

        $subscription = Subscription::query()->where('user_id', $customer->id)->sole();
        $this->assertSame(BillingMode::ManualInvoice, $subscription->billing_mode);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(3, $subscription->delivery_weekday);

        $event = AuditEvent::query()
            ->where('entity_type', 'subscription')
            ->where('entity_id', $subscription->id)
            ->where('action', 'admin_create')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame(BillingMode::ManualInvoice->value, $event->metadata['billing_mode'] ?? null);
    }

    public function test_admin_cannot_create_subscription_for_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.subscriptions.create'))
            ->post(route('app.admin.subscriptions.store'), [
                'user_id' => $otherAdmin->id,
                'billing_mode' => BillingMode::ManualInvoice->value,
                'plan' => 'weekly',
                'box_size' => 'medium',
                'fulfillment_mode' => 'delivery',
                'delivery_weekday' => 3,
                'preferred_timeslot' => Timeslot::Afternoon->value,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_charge_cron_skips_manual_invoice_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('app.admin.subscriptions.store'), [
                'user_id' => $customer->id,
                'billing_mode' => BillingMode::ManualInvoice->value,
                'plan' => 'weekly',
                'box_size' => 'medium',
                'fulfillment_mode' => 'pickup',
                'preferences' => [],
            ])
            ->assertRedirect(route('app.admin.subscriptions.index'));

        $subscription = Subscription::query()->where('user_id', $customer->id)->sole();
        $subscription->update(['next_charge_date' => now()->subDay()->toDateString()]);

        $this->artisan(ChargeSubscriptions::class)->assertExitCode(0);

        $this->assertSame(BillingMode::ManualInvoice, $subscription->fresh()->billing_mode);
        $this->assertFalse(Payment::query()->where('subscription_id', $subscription->id)->exists(),
            'ManualInvoice subscriptions must not produce a Payment row via the recurring cron.');
    }
}
