<?php

namespace App\Mail;

use App\Models\SpargelOrder;
use App\Observers\SpargelOrderObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to every is_admin user when a SpargelOrder transitions to PAID.
 *
 * Triggered by {@see SpargelOrderObserver::updated()} on the
 * status change from any non-paid state to `paid`. Includes the full order
 * payload — admins triage from this email without needing to open the
 * dashboard.
 *
 * Recipients are resolved at dispatch time (one Mailable per admin so each
 * envelope has a single `To:` and the audit trail is clean).
 */
class SpargelOrderPaidAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SpargelOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Erdgut] Spargel order #'.$this->order->id.' paid — €'.number_format((float) $this->order->total_amount_eur, 2, '.', ','),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.spargel-order-paid-admin',
            with: [
                'order' => $this->order,
            ],
        );
    }
}
