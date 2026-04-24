<?php

namespace App\Mail;

use App\Models\Box;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoxComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Box $box) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.box_composed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.box-composed',
            text: 'emails.box-composed-text',
            with: [
                'box' => $this->box,
            ],
        );
    }
}
