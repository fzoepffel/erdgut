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
 * Sent to every is_admin user the moment a SpargelOrder is created
 * (status = pending_payment), before the customer has paid.
 *
 * Triggered by {@see SpargelOrderObserver::created()}. Two reasons we
 * notify on creation as well as on paid:
 *
 * 1. payment_method = in_person never transitions through SumUp — the
 *    customer pays at pickup. Without this email, admins have no
 *    out-of-band signal that an in-person order is queued.
 * 2. SumUp can stall mid-flow (cf. Arthur's mobile attempts on 2026-05-04
 *    where 4 of 5 sessions left orders stuck in pending_payment forever).
 *    The opened-notification + the matching `paid` follow-up make stalls
 *    obvious — when an opened email arrives but the paid email never does.
 *
 * The follow-up "paid" email is in {@see SpargelOrderPaidAdminNotification}.
 */
class SpargelOrderOpenedAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SpargelOrder $order) {}

    public function envelope(): Envelope
    {
        $methodSuffix = $this->order->payment_method === 'in_person'
            ? 'vor Ort'
            : 'SumUp';

        return new Envelope(
            subject: '[Erdgut] Neue Spargel-Bestellung #'.$this->order->id.' eingegangen — '.number_format((float) $this->order->total_amount_eur, 2, ',', '.').' € · '.$methodSuffix,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.spargel-order-opened-admin',
            with: [
                'order' => $this->order,
            ],
        );
    }
}
