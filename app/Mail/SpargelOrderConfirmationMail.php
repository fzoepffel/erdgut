<?php

namespace App\Mail;

use App\Models\SpargelOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email for the spargel trial-shop. Sent when:
 *  - online order: SumUp confirms the payment via the return URL
 *  - in-person order: customer presses "confirm" through the modal
 *
 * Standalone — does NOT pull in the existing Mail layout / branding
 * components used by Order/Subscription mail. Self-contained inline
 * styling so it never accidentally drifts with the main app's email
 * stack and so we can rip it out cleanly when the proper shop launches.
 */
class SpargelOrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SpargelOrder $order) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->order->payment_method === 'in_person'
            ? 'spargel.email_subject_in_person'
            : 'spargel.email_subject_paid';

        return new Envelope(
            subject: __($subjectKey),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'spargel.emails.confirmation',
            with: [
                'order' => $this->order,
            ],
        );
    }
}
