<?php

namespace App\Notifications;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Delivery $delivery
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->delivery->scheduled_for_date?->translatedFormat('l, d. F Y') ?? '';
        $timeslot = $this->delivery->timeslot ?? '';
        $address = $this->formatAddress();
        $instructions = $this->delivery->special_instructions;

        return (new MailMessage)
            ->subject(__('Lieferung morgen') . ' – ' . config('app.name'))
            ->view('emails.delivery-reminder', [
                'date' => $date,
                'timeslot' => $timeslot,
                'address' => $address,
                'instructions' => $instructions,
            ]);
    }

    private function formatAddress(): string
    {
        $addr = $this->delivery->address;

        if ($addr === null) {
            return __('Adresse nicht angegeben');
        }

        $parts = array_filter([
            $addr->recipient_name,
            $addr->street_line_1,
            $addr->street_line_2,
            $addr->postal_code ? $addr->postal_code . ' ' . $addr->city : $addr->city,
            $addr->country_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'scheduled_for_date' => $this->delivery->scheduled_for_date?->toDateString(),
        ];
    }
}
