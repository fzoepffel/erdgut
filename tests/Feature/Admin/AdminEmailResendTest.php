<?php

namespace Tests\Feature\Admin;

use App\Mail\BoxComposedMail;
use App\Mail\DeliveredMail;
use App\Mail\DeliveryInTransitMail;
use App\Mail\SubscriptionActivatedMail;
use App\Mail\SubscriptionCancellationMail;
use App\Mail\SubscriptionPauseExhaustedMail;
use App\Mail\SubscriptionSkipConfirmationMail;
use App\Mail\TrialConvertToSubscriptionMail;
use App\Mail\TrialDeliveryScheduledMail;
use App\Mail\TrialOrderConfirmationMail;
use App\Models\AuditEvent;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ERD-332: admin can re-dispatch any known transactional email. Each resend
 * must queue the correct Mailable to the entity's user and write an audit row.
 */
class AdminEmailResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->post(route('app.admin.emails.resend'), [
            'template' => 'subscription.activated',
            'subscription_id' => 0,
        ])->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.activated',
                'subscription_id' => 1,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_resend_subscription_activated(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'recv@example.com']);
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.activated',
                'subscription_id' => $subscription->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'admin-email-resent');

        Mail::assertQueued(SubscriptionActivatedMail::class, fn ($m) => $m->hasTo($customer->email));

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'subscription',
            'entity_id' => $subscription->id,
            'action' => 'email_resend',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_resend_skip_confirmation_with_current_user_weeks(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        $monday = CarbonImmutable::today()->startOfWeek()->addWeek()->toDateString();
        SubscriptionSkipWeek::query()->create([
            'subscription_id' => $subscription->id,
            'week_start_date' => $monday,
            'source' => SubscriptionSkipWeek::SOURCE_USER,
        ]);
        // Another week that should be ignored because source != user.
        SubscriptionSkipWeek::query()->create([
            'subscription_id' => $subscription->id,
            'week_start_date' => CarbonImmutable::parse($monday)->addWeek()->toDateString(),
            'source' => SubscriptionSkipWeek::SOURCE_PAUSE_WINDOW,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.skip_confirmation',
                'subscription_id' => $subscription->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'admin-email-resent');

        Mail::assertQueued(SubscriptionSkipConfirmationMail::class, function (SubscriptionSkipConfirmationMail $m) use ($monday) {
            return $m->skipWeekStartDates === [$monday];
        });
    }

    public function test_admin_can_resend_subscription_cancellation(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.cancellation',
                'subscription_id' => $subscription->id,
            ])
            ->assertRedirect();

        Mail::assertQueued(SubscriptionCancellationMail::class);
    }

    public function test_admin_can_resend_pause_exhausted(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.pause_exhausted',
                'subscription_id' => $subscription->id,
            ])
            ->assertRedirect();

        Mail::assertQueued(SubscriptionPauseExhaustedMail::class);
    }

    public function test_admin_can_resend_box_composed(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'boxrecv@example.com']);
        $box = Box::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'box.composed',
                'box_id' => $box->id,
            ])
            ->assertRedirect();

        Mail::assertQueued(BoxComposedMail::class, fn ($m) => $m->hasTo($customer->email));

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'box',
            'entity_id' => $box->id,
            'action' => 'email_resend',
        ]);
    }

    public function test_admin_can_resend_delivery_in_transit(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'delivery.in_transit',
                'delivery_id' => $delivery->id,
            ])
            ->assertRedirect();

        Mail::assertQueued(DeliveryInTransitMail::class);
    }

    public function test_admin_can_resend_delivery_delivered(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'delivery.delivered',
                'delivery_id' => $delivery->id,
            ])
            ->assertRedirect();

        Mail::assertQueued(DeliveredMail::class);
    }

    public function test_admin_can_resend_order_trial_templates(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        foreach (['order.trial_confirmation' => TrialOrderConfirmationMail::class,
            'order.trial_delivery_scheduled' => TrialDeliveryScheduledMail::class,
            'order.trial_convert' => TrialConvertToSubscriptionMail::class,
        ] as $template => $mailable) {
            $this->actingAs($admin)
                ->from(route('app.admin.deliveries.index'))
                ->post(route('app.admin.emails.resend'), [
                    'template' => $template,
                    'order_id' => $order->id,
                ])
                ->assertRedirect();

            Mail::assertQueued($mailable);
        }
    }

    public function test_missing_recipient_email_returns_not_found_flash(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        // Simulate a user record without a usable email address; the controller
        // short-circuits via the not-found flash instead of queueing to "".
        $customer->forceFill(['email' => ''])->save();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.activated',
                'subscription_id' => $subscription->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'admin-email-resend-not-found');

        Mail::assertNothingQueued();
    }

    public function test_validation_rejects_unknown_template(): void
    {
        $admin = User::factory()->admin()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'not.a.real.template',
                'subscription_id' => $subscription->id,
            ])
            ->assertSessionHasErrors('template');
    }

    public function test_audit_event_metadata_records_template_and_recipient(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'audit@example.com']);
        $subscription = Subscription::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.emails.resend'), [
                'template' => 'subscription.activated',
                'subscription_id' => $subscription->id,
            ]);

        $event = AuditEvent::query()
            ->where('entity_type', 'subscription')
            ->where('entity_id', $subscription->id)
            ->where('action', 'email_resend')
            ->firstOrFail();

        $metadata = (array) $event->metadata;
        $this->assertSame('subscription.activated', $metadata['template'] ?? null);
        $this->assertSame('audit@example.com', $metadata['recipient'] ?? null);
    }
}
