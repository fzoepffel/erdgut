<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialOrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Erdgut trial box is confirmed'
        );
    }

    public function content(): Content
    {
        $promiseDays = (int) config('box.trial_scheduling_promise_days', 14);

        return new Content(
            view: 'emails.trial-order-confirmation',
            with: [
                'order' => $this->order,
                'promiseDays' => $promiseDays,
            ],
        );
    }
}
