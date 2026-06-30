<?php

namespace App\Mail;

use App\Models\RestaurantOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a restaurant when an admin declines their request.
 */
class RestaurantOrderDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantOrder $order, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('restaurant.order_email.declined_subject', ['id' => $this->order->id]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.order-declined',
            with: [
                'order' => $this->order,
                'note' => $this->note,
            ],
        );
    }
}
