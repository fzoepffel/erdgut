<?php

namespace App\Mail;

use App\Models\RestaurantOrder;
use App\Services\Restaurant\RestaurantInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a restaurant when an admin issues their invoice. The Rechnung PDF is
 * attached; a rendering failure is swallowed so it never blocks the email.
 */
class RestaurantOrderInvoicedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('restaurant.order_email.invoiced_subject', ['number' => $this->order->invoice_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.order-invoiced',
            with: [
                'order' => $this->order,
                'url' => route('app.restaurant.orders.show', $this->order),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        try {
            $pdf = app(RestaurantInvoiceService::class)->renderPdf($this->order);

            return [
                Attachment::fromData(fn () => $pdf->output(), 'erdgut-rechnung-'.$this->order->invoice_number.'.pdf')
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
