<?php

namespace Tests\Feature\StressFlows;

use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionPauseExhaustedMail;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Stress-flow 12 (ERD-342): Payment fails → retries exhaust → subscription
 * pauses. Outcome: after max-retries failures, subscriptions:pause-exhausted
 * pauses the subscription and emails the user.
 */
class Flow12PaymentFailureRetryPauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_exhausted_retries_pause_subscription_and_email_user(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'paused@example.com']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_failing_123',
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => (int) config('payments.max_retries', 3),
            'failure_reason' => 'card_declined',
        ]);

        $this->artisan('subscriptions:pause-exhausted')->assertSuccessful();

        $this->assertSame(
            SubscriptionStatus::Paused,
            $subscription->fresh()->status,
            'Subscription should pause after exhausted retries.'
        );

        Mail::assertQueued(SubscriptionPauseExhaustedMail::class, function (SubscriptionPauseExhaustedMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
