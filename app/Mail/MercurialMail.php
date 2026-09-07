<?php

namespace App\Mail;

use App\Support\MercurialHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One weekly mercurial, addressed to a single restaurant customer. Queued so a
 * broadcast to the whole list never blocks the request. The subject and body
 * are already personalized (merge tokens substituted per recipient) and the
 * body HTML is sanitized by {@see MercurialHtml}. Sent from the
 * customer-facing kontakt@ mailbox rather than the default noreply sender.
 */
class MercurialMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mercurial.from_address'),
                (string) config('mercurial.from_name'),
            ),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mercurial',
            with: [
                'subjectLine' => $this->subjectLine,
                'bodyHtml' => $this->bodyHtml,
            ],
        );
    }
}
