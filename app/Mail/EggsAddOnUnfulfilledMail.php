<?php

namespace App\Mail;

use App\Models\Box;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EggsAddOnUnfulfilledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Box $box,
        public float $refundAmountEur,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.eggs_unfulfilled.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.eggs-unfulfilled',
            with: [
                'user' => $this->user,
                'box' => $this->box,
                'refundAmountEur' => $this->refundAmountEur,
            ],
        );
    }
}
