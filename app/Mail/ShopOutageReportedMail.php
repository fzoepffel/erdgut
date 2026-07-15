<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A visitor hit the storefront's "nothing in stock" screen and pressed
 * the notify button. Since the shop is expected to always carry stock,
 * this most likely means materialization/cron/data broke: treat as an
 * incident signal for the admins.
 */
class ShopOutageReportedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $reportedAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ALARM: Hofladen zeigt "Nichts auf Lager"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'shop.emails.outage-reported',
            with: ['reportedAt' => $this->reportedAt],
        );
    }
}
