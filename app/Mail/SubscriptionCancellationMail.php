<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.cancellation.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cancellation',
            text: 'emails.cancellation-text',
            with: [
                'subscription' => $this->subscription,
            ],
        );
    }
}
