<?php

namespace App\Mail;

use App\Models\ShopOrder;
use App\Observers\ShopOrderObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to every is_admin user once a {@see ShopOrder} becomes a *confirmed
 * sale* ("finalized"):
 *
 *  - in-person orders: the moment checkout completes (status PendingPayment,
 *    payment paid at pickup) — a real order the farm must prepare;
 *  - online orders: once SumUp confirms payment (status flips to Paid).
 *
 * Unfinished online checkouts (guest sent to SumUp but never paid) deliberately
 * do NOT trigger this email — that is the noise we want to avoid. Dispatched by
 * {@see ShopOrderObserver}. One Mailable per admin so every envelope has a
 * single `To:` and the audit trail stays clean. Mirrors the spargel admin
 * notification pattern generalized to multi-product orders.
 */
class ShopOrderFinalizedAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ShopOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Erdgut] Shop order #'.$this->order->id.' confirmed — €'.number_format((float) $this->order->total_amount_eur, 2, '.', ','),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shop-order-finalized-admin',
            with: [
                'order' => $this->order,
            ],
        );
    }
}
