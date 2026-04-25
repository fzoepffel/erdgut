<?php

namespace Tests\Feature\StressFlows;

use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionCancellationMail;
use App\Models\AuditEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Stress-flow 6 (ERD-342): Cancel a subscription with notice. Outcome:
 * status flips to Cancelled, an audit_events row is written via
 * AuditLogger ('subscription' / 'cancelled'), and a cancellation
 * confirmation email is queued for the user.
 */
class Flow06CancelWithNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_writes_audit_event_and_emails_user(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'leaving@example.com']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        app(SubscriptionLifecycleService::class)->cancelNow(
            $subscription,
            'too expensive right now',
            $user->id
        );

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);

        $event = AuditEvent::where('entity_type', 'subscription')
            ->where('entity_id', $subscription->id)
            ->where('action', 'cancelled')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame($user->id, $event->actor_user_id);
        $this->assertSame('too expensive right now', $event->reason);

        Mail::assertQueued(SubscriptionCancellationMail::class, function (SubscriptionCancellationMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
