<?php

namespace App\Mail;

use App\Models\RestaurantProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to every admin when a restaurant applies. Carries a signed review
 * link (one per admin) to verify or reject the application.
 */
class RestaurantApplicationAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RestaurantProfile $profile,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Erdgut] Neue Restaurant-Anfrage: '.$this->profile->business_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant.application-admin',
            with: [
                'profile' => $this->profile,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }
}
