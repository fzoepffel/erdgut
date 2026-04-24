<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionPauseExhaustedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.pause_exhausted.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pause-exhausted',
            text: 'emails.pause-exhausted-text',
            with: [
                'subscription' => $this->subscription,
            ],
        );
    }
}
