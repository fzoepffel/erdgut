<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialConvertToSubscriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Loved your trial? Keep Erdgut coming every week'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-convert-to-subscription',
            with: ['order' => $this->order],
        );
    }
}
