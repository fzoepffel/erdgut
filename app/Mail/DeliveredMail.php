<?php

namespace App\Mail;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Delivery $delivery) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.delivered.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.delivered',
            text: 'emails.delivered-text',
            with: [
                'delivery' => $this->delivery,
            ],
        );
    }
}
