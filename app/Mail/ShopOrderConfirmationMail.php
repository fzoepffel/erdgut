<?php

namespace App\Mail;

use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email for an anonymous shop order. Sent when:
 *  - online order: SumUp confirms the payment via the return URL
 *  - in-person order: the order is placed (paid on pickup)
 *
 * Standalone — like {@see SpargelOrderConfirmationMail} it does NOT pull
 * in the account/subscription mail layout, keeping the anonymous shop
 * channel self-contained and easy to evolve independently.
 */
class ShopOrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShopOrder $order) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->order->payment_method === 'in_person'
            ? 'shop.email_subject_in_person'
            : 'shop.email_subject_paid';

        return new Envelope(
            subject: __($subjectKey),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'shop.emails.confirmation',
            with: [
                'order' => $this->order->loadMissing('items.product.translations'),
            ],
        );
    }
}
