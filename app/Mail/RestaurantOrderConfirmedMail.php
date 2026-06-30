<?php

namespace App\Mail;

use App\Models\RestaurantOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a restaurant when an admin confirms their request.
 */
class RestaurantOrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantOrder $order, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('restaurant.order_email.confirmed_subject', ['id' => $this->order->id]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.order-confirmed',
            with: [
                'order' => $this->order->loadMissing('items.product.translations'),
                'note' => $this->note,
                'url' => route('app.restaurant.orders.show', $this->order),
            ],
        );
    }
}
