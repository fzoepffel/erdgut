<?php

namespace App\Mail;

use App\Models\RestaurantOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a restaurant when their order/request is submitted — confirms receipt
 * and lists the lines (reserved vs on request) with the net total.
 */
class RestaurantOrderSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantOrder $order) {}

    public function envelope(): Envelope
    {
        // Shop orders are confirmed the moment they're placed; requests are
        // received and await the admin.
        $key = $this->order->isRequest()
            ? 'restaurant.order_email.submitted_subject'
            : 'restaurant.order_email.confirmed_subject';

        return new Envelope(subject: __($key, ['id' => $this->order->id]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.order-submitted',
            with: [
                'order' => $this->order->loadMissing('items.product.translations'),
                'url' => route('app.restaurant.orders.show', $this->order),
            ],
        );
    }
}
