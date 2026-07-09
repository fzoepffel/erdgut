<?php

namespace App\Mail;

use App\Models\RestaurantOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to each admin when a restaurant submits a new request.
 */
class RestaurantOrderAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantOrder $order, public bool $adjusted = false) {}

    public function envelope(): Envelope
    {
        if ($this->adjusted) {
            return new Envelope(
                subject: '[Erdgut] Restaurant-Bestellung #'.$this->order->id.' geändert (Kunde)',
            );
        }

        return new Envelope(
            subject: $this->order->isRequest()
                ? '[Erdgut] Neue Restaurant-Anfrage #'.$this->order->id
                : '[Erdgut] Neue Restaurant-Bestellung #'.$this->order->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.order-admin',
            with: [
                'order' => $this->order->loadMissing(['user', 'items.product.translations']),
            ],
        );
    }
}
