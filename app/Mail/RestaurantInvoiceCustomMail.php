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
 * The invoice email as composed in the admin's mail editor: subject and
 * body are free text (the admin edited or approved them before sending).
 * The Rechnung PDF is attached; a rendering failure is swallowed so it
 * never blocks the email.
 */
class RestaurantInvoiceCustomMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RestaurantOrder $order,
        public string $mailSubject,
        public string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.invoice-custom',
            with: [
                'body' => $this->mailBody,
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
