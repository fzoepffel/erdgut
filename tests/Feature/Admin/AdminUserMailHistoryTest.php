<?php

namespace Tests\Feature\Admin;

use App\Mail\SubscriptionActivatedMail;
use App\Models\AuditEvent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ERD-350: per-user mail history surfaces in /app/admin/users/{user}.
 *
 * Listener writes a `mail.sent` AuditEvent against the recipient user;
 * the user-detail audit timeline renders the row with a friendly
 * subject + recipient line. Tests guard the listener behaviour and
 * the controller query that picks up the new event-type.
 */
class AdminUserMailHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_mailable_to_a_known_user_writes_a_mail_sent_audit_event(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
        ]);

        Mail::to($user->email)->send(new SubscriptionActivatedMail($subscription));

        $event = AuditEvent::query()
            ->where('entity_type', 'user')
            ->where('entity_id', $user->id)
            ->where('action', 'mail.sent')
            ->latest('id')
            ->first();

        $this->assertNotNull($event, 'mail.sent audit event was not recorded');
        $this->assertSame(strtolower($user->email), data_get($event->metadata, 'recipient_email'));
        $this->assertNotEmpty(data_get($event->metadata, 'subject'));
    }

    public function test_sending_to_an_address_with_no_user_record_is_silently_skipped(): void
    {
        $subscription = Subscription::factory()->create();

        // Use a Mailable that doesn't depend on a real recipient user (the
        // SubscriptionActivatedMail composes content but doesn't lookup the
        // recipient address by user, so this is safe).
        Mail::to('guest-no-user@example.test')->send(new SubscriptionActivatedMail($subscription));

        $this->assertSame(
            0,
            AuditEvent::query()->where('action', 'mail.sent')->count(),
            'Anonymous recipient should not produce a mail.sent event'
        );
    }

    public function test_user_detail_audit_timeline_includes_the_mail_sent_row(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $customer->id,
        ]);

        Mail::to($customer->email)->send(new SubscriptionActivatedMail($subscription));

        $html = $this->actingAs($admin)
            ->get(route('app.admin.users.show', $customer))
            ->assertOk()
            ->getContent();

        // Friendly mail-event row carries the email label + the recipient line.
        $this->assertStringContainsString(__('admin.user_detail.audit_mail_label'), $html);
        $this->assertStringContainsString($customer->email, $html);
    }
}
