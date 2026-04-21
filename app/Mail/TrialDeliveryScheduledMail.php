<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialDeliveryScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Erdgut trial box delivery date is set'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-delivery-scheduled',
            with: ['order' => $this->order],
        );
    }
}
