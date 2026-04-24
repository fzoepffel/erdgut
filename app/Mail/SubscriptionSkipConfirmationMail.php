<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionSkipConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $skipWeekStartDates  ISO Monday dates, newest rendering delegated to view
     */
    public function __construct(
        public Subscription $subscription,
        public array $skipWeekStartDates = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.skip_confirmation.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.skip-confirmation',
            text: 'emails.skip-confirmation-text',
            with: [
                'subscription' => $this->subscription,
                'skipWeekStartDates' => $this->skipWeekStartDates,
            ],
        );
    }
}
