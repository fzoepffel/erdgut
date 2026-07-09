<?php

namespace App\Mail;

use App\Models\RestaurantProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a restaurant when an admin verifies their account — it is now open.
 */
class RestaurantApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantProfile $profile) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('restaurant.email.approved_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.approved',
            with: [
                'profile' => $this->profile,
                'loginUrl' => route('auth.login'),
            ],
        );
    }
}
