<?php

namespace App\Listeners;

use App\Contracts\SentMailArchive;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

/**
 * Copies every outgoing mail into the IMAP "Sent" folder of the mailbox it
 * was sent from, so site mail is visible in IONOS webmail / any mail client.
 *
 * SMTP submission never populates a Sent folder; this listener bridges that
 * gap by APPENDing the raw sent message over IMAP. It reads the From address
 * off the message at send time, so a mail sent as kontakt@ lands in kontakt@'s
 * Sent folder and one sent as noreply@ in noreply@'s — no per-mailable wiring.
 *
 * Best-effort: any failure is reported and swallowed so archiving can never
 * break (or slow to failure) an outbound send that already succeeded.
 */
class ArchiveSentMailToImap
{
    public function __construct(private readonly SentMailArchive $archive) {}

    public function handle(MessageSent $event): void
    {
        try {
            $from = $event->message->getFrom();
            $fromAddress = $from[0] ?? null;

            if ($fromAddress === null) {
                return;
            }

            $this->archive->store(
                (string) $fromAddress->getAddress(),
                $event->message->toString(),
            );
        } catch (Throwable $e) {
            // Archiving is best-effort; never let it affect the send.
            report($e);
        }
    }
}
