<?php

namespace App\Mail;

use App\Models\ShopOrder;
use App\Services\Shop\ShopOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your delivery / pickup window is set" email. Sent when an admin publishes
 * (or changes) the time window from the shop-orders list. Standalone +
 * inline-styled like {@see ShopOrderConfirmationMail} so the anonymous shop
 * channel stays self-contained.
 */
class ShopOrderWindowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShopOrder $order) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->order->fulfillment_mode === 'delivery'
            ? 'shop.window_email_subject_delivery'
            : 'shop.window_email_subject_pickup';

        return new Envelope(
            subject: __($subjectKey),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'shop.emails.window',
            with: [
                'order' => $this->order->loadMissing('items.product.translations'),
                'trackUrl' => ShopOrderService::trackUrl($this->order),
            ],
        );
    }
}
