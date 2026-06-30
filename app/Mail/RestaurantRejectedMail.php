<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to an applicant when an admin rejects their restaurant application.
 * Holds scalars only — the user + profile are deleted on rejection, so there
 * is no model to serialize.
 */
class RestaurantRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $businessName,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('restaurant.email.rejected_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.rejected',
            with: [
                'businessName' => $this->businessName,
                'reason' => $this->reason,
            ],
        );
    }
}
