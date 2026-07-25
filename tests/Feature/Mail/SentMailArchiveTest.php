<?php

namespace Tests\Feature\Mail;

use App\Contracts\SentMailArchive;
use App\Listeners\ArchiveSentMailToImap;
use App\Support\ImapSentMailArchive;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage as IlluminateSentMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SentMailArchiveTest extends TestCase
{
    /**
     * Build a real MessageSent event for a mail from $from, so the listener
     * runs against genuine Symfony objects (getFrom / toString) without
     * routing through the dispatcher.
     */
    private function messageSentFrom(string $from, string $subject): MessageSent
    {
        $email = (new Email)
            ->from($from)
            ->to('customer@example.com')
            ->subject($subject)
            ->text('Body');

        $sent = new IlluminateSentMessage(new SymfonySentMessage(
            $email,
            new Envelope(new Address($from), [new Address('customer@example.com')]),
        ));

        return new MessageSent($sent);
    }

    /** @return object&SentMailArchive spy recording every store() call */
    private function spyArchive(): object
    {
        return new class implements SentMailArchive
        {
            /** @var list<array{from: string, raw: string}> */
            public array $calls = [];

            public function store(string $fromAddress, string $rawMessage): bool
            {
                $this->calls[] = ['from' => $fromAddress, 'raw' => $rawMessage];

                return true;
            }
        };
    }

    public function test_sent_mail_is_offered_to_the_archive_with_its_from_address(): void
    {
        $spy = $this->spyArchive();

        (new ArchiveSentMailToImap($spy))->handle(
            $this->messageSentFrom('noreply@erd-gut.de', 'Ihre Rechnung')
        );

        $this->assertCount(1, $spy->calls);
        $this->assertSame('noreply@erd-gut.de', $spy->calls[0]['from']);
        $this->assertStringContainsString('Subject: Ihre Rechnung', $spy->calls[0]['raw']);
    }

    public function test_mail_sent_from_kontakt_is_archived_under_kontakt(): void
    {
        $spy = $this->spyArchive();

        (new ArchiveSentMailToImap($spy))->handle(
            $this->messageSentFrom('kontakt@erd-gut.de', 'Kontakt')
        );

        $this->assertCount(1, $spy->calls);
        $this->assertSame('kontakt@erd-gut.de', $spy->calls[0]['from']);
    }

    public function test_archive_is_disabled_by_default(): void
    {
        $archive = new ImapSentMailArchive([
            'enabled' => false,
            'accounts' => [
                'noreply@erd-gut.de' => ['username' => 'noreply@erd-gut.de', 'password' => 'secret'],
            ],
        ]);

        $this->assertFalse($archive->store('noreply@erd-gut.de', "Subject: x\r\n\r\nbody"));
    }

    public function test_archive_resolves_the_account_for_a_known_sender(): void
    {
        $archive = new ImapSentMailArchive([
            'enabled' => true,
            'accounts' => [
                'noreply@erd-gut.de' => ['username' => 'noreply@erd-gut.de', 'password' => 'secret'],
            ],
        ]);

        // Case-insensitive on the From address.
        $account = $archive->resolveAccount('NoReply@Erd-Gut.de');

        $this->assertNotNull($account);
        $this->assertSame('noreply@erd-gut.de', $account['username']);
        $this->assertSame('secret', $account['password']);
    }

    public function test_archive_skips_a_sender_without_a_configured_mailbox(): void
    {
        $archive = new ImapSentMailArchive([
            'enabled' => true,
            'accounts' => [
                'noreply@erd-gut.de' => ['username' => 'noreply@erd-gut.de', 'password' => 'secret'],
            ],
        ]);

        $this->assertNull($archive->resolveAccount('stranger@example.com'));
        // store() returns false (no throw) rather than reaching for IMAP.
        $this->assertFalse($archive->store('stranger@example.com', "Subject: x\r\n\r\nbody"));
    }
}
