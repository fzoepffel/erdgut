<?php

namespace App\Mail;

use App\Models\ShopOrder;
use App\Services\Shop\ShopOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email for an anonymous shop order. Sent when:
 *  - online order: SumUp confirms the payment via the return URL
 *  - in-person order: the order is placed (paid on pickup)
 *
 * A PDF Kaufbeleg (Kleinbetragsrechnung) is attached so the customer has
 * a downloadable receipt immediately without needing to revisit the site.
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
                'order' => $this->order->loadMissing([
                    'items.product.translations',
                    'items.product.producer',
                ]),
                'trackUrl' => ShopOrderService::trackUrl($this->order),
            ],
        );
    }

    /**
     * Attach the PDF receipt (Kleinbetragsrechnung) generated on-the-fly.
     * Rendered in German regardless of the customer's locale — it is a
     * German tax document. Any rendering failure is swallowed silently so
     * a PDF issue never blocks the confirmation email itself.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        try {
            $order = $this->order->loadMissing([
                'items.product.translations',
                'items.product.producer',
            ]);

            $pdf = Pdf::loadView('shop.pdf.receipt', [
                'order' => $order,
                'vatByRate' => ShopOrderService::vatByRate($order),
                'company' => [
                    'name' => (string) config('shop.company_name'),
                    'street' => (string) config('shop.company_street'),
                    'postal_city' => (string) config('shop.company_postal_city'),
                    'phone' => (string) config('shop.company_phone'),
                    'email' => (string) config('shop.company_email'),
                    'vat_id' => (string) config('shop.company_vat_id'),
                ],
            ])->setPaper('a4');

            return [
                Attachment::fromData(
                    fn () => $pdf->output(),
                    'erdgut-beleg-'.$order->id.'.pdf',
                )->withMime('application/pdf'),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
