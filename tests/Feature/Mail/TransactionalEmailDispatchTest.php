<?php

namespace Tests\Feature\Mail;

use App\Console\Commands\PauseExhaustedSubscriptions;
use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\BoxComposedMail;
use App\Mail\DeliveredMail;
use App\Mail\DeliveryInTransitMail;
use App\Mail\SubscriptionCancellationMail;
use App\Mail\SubscriptionPauseExhaustedMail;
use App\Mail\SubscriptionSkipConfirmationMail;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BoxCompositionService;
use App\Support\SubscriptionLifecycleService;
use App\Support\SubscriptionSkipService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ERD-339: end-to-end assertions that each lifecycle hook queues the right
 * transactional Mailable. We don't test the rendered content here — just that
 * the correct recipient + Mailable pair reaches the mail queue.
 */
class TransactionalEmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_box_composition_queues_box_composed_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'box@example.com']);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'allergies_snapshot' => [],
            'preferences_snapshot' => [],
        ]);

        // Seed a small product catalog so the composition rules can pick lines.
        Product::factory()->count(5)->create([
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
        ]);

        Config::set('box.composition_by_size.small', ['vegetables' => 2]);

        $service = app(BoxCompositionService::class);
        $added = $service->compose($box, 'small');

        $this->assertGreaterThan(0, $added);
        Mail::assertQueued(BoxComposedMail::class, function (BoxComposedMail $mail) use ($box, $user) {
            return $mail->hasTo($user->email) && $mail->box->is($box);
        });
    }

    public function test_box_composition_does_not_queue_mail_when_no_items_added(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $box = Box::factory()->create(['user_id' => $user->id]);

        // No composition rules + no catalog -> compose() returns 0 adds.
        Config::set('box.composition_by_size.small', []);

        $service = app(BoxCompositionService::class);
        $service->compose($box, 'small');

        Mail::assertNothingQueued();
    }

    public function test_admin_status_update_queues_in_transit_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $delivery = Delivery::factory()->create([
            'user_id' => $customer->id,
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'in_transit'])
            ->assertRedirect();

        Mail::assertQueued(DeliveryInTransitMail::class, function (DeliveryInTransitMail $mail) use ($customer, $delivery) {
            return $mail->hasTo($customer->email) && $mail->delivery->is($delivery);
        });
        Mail::assertNotQueued(DeliveredMail::class);
    }

    public function test_admin_status_update_queues_delivered_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $delivery = Delivery::factory()->create([
            'user_id' => $customer->id,
            'status' => DeliveryStatus::InTransit,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertRedirect();

        Mail::assertQueued(DeliveredMail::class);
        Mail::assertNotQueued(DeliveryInTransitMail::class);
    }

    public function test_admin_status_update_without_status_change_queues_nothing(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Delivered,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered']);

        Mail::assertNothingQueued();
    }

    public function test_skip_weeks_sync_queues_confirmation_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'skipper@example.com']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $monday = CarbonImmutable::today()->startOfWeek()->addWeeks(2)->toDateString();

        app(SubscriptionSkipService::class)
            ->syncUserSkipWeeks($subscription, [$monday], $user->id);

        Mail::assertQueued(SubscriptionSkipConfirmationMail::class, function (SubscriptionSkipConfirmationMail $mail) use ($user, $monday) {
            return $mail->hasTo($user->email)
                && in_array($monday, $mail->skipWeekStartDates, true);
        });
    }

    public function test_cancel_now_queues_cancellation_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'cancel@example.com']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionLifecycleService::class)
            ->cancelNow($subscription, 'Too many deliveries', $user->id);

        Mail::assertQueued(SubscriptionCancellationMail::class, function (SubscriptionCancellationMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_pause_exhausted_command_queues_pause_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'exhausted@example.com']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_live_example',
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => (int) config('payments.max_retries', 3),
            'failure_reason' => 'card_declined',
        ]);

        $this->artisan('subscriptions:pause-exhausted')->assertSuccessful();

        Mail::assertQueued(SubscriptionPauseExhaustedMail::class, function (SubscriptionPauseExhaustedMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_pause_exhausted_dry_run_queues_nothing(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_live_example',
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => (int) config('payments.max_retries', 3),
        ]);

        $this->artisan(PauseExhaustedSubscriptions::class, ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
