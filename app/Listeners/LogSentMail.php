<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

/**
 * Records every outbound Mailable as an `mail.sent` audit event so the
 * per-user admin page (/app/admin/users/{user}) can answer support
 * questions like "did this customer get the trial confirmation email?"
 * without grepping logs.
 *
 * Strategy: read the To: addresses from the Symfony Email, find a User
 * by email, write an AuditEvent against that user. For To: addresses
 * that don't match any User (e.g. a shop confirmation to a guest checkout
 * with no account), the event is silently skipped — those use cases
 * live elsewhere (e.g. SpargelOrder admin already has its own paid_at
 * timestamp + status).
 *
 * actor_user_id is auto-resolved by AuditLogger via Auth::id(), so
 * admin-triggered resends naturally attribute to the admin who clicked
 * "Resend"; system-driven sends (queue worker, scheduler) record null.
 */
class LogSentMail
{
    public function handle(MessageSent $event): void
    {
        try {
            $subject = (string) ($event->message->getSubject() ?? '');
            $recipients = $this->extractToAddresses($event);

            if ($recipients === []) {
                return;
            }

            $userIds = User::query()
                ->whereIn('email', $recipients)
                ->pluck('id', 'email');

            foreach ($recipients as $email) {
                $userId = $userIds->get($email);
                if ($userId === null) {
                    continue;
                }

                AuditLogger::log(
                    entityType: 'user',
                    entityId: (int) $userId,
                    action: 'mail.sent',
                    metadata: [
                        'recipient_email' => $email,
                        'subject' => $subject,
                    ],
                );
            }
        } catch (Throwable $e) {
            // Audit logging is best-effort. AuditLogger already swallows
            // its own exceptions; this catch protects against EVERYTHING
            // (e.g. a Symfony Mime parse error) so a logging hiccup never
            // breaks an outbound email.
            report($e);
        }
    }

    /**
     * @return list<string>
     */
    private function extractToAddresses(MessageSent $event): array
    {
        $to = $event->message->getTo();
        $emails = [];
        foreach ($to as $address) {
            $email = strtolower((string) $address->getAddress());
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }
}
