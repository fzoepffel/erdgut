<?php

namespace App\Mail;

use App\Models\ShopOfferSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a waitlist subscriber when the full shop offer returns. Always
 * carries a one-click unsubscribe link that removes them from the list.
 */
class ShopOfferReopenedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShopOfferSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.shop_offer_reopened.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shop-offer-reopened',
            text: 'emails.shop-offer-reopened-text',
            with: [
                'shopUrl' => route('public.shop.index'),
                'unsubscribeUrl' => $this->subscriber->unsubscribeUrl(),
            ],
        );
    }
}
