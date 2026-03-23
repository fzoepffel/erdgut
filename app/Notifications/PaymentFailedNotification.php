<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment
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
        $amount = number_format((float) $this->payment->amount, 2, ',', '.');
        $currency = $this->payment->currency ?? 'EUR';
        $updateUrl = route('app.payments.update');

        return (new MailMessage)
            ->subject(__('Zahlung fehlgeschlagen').' – '.config('app.name'))
            ->view('emails.payment-failed', [
                'amount' => $amount,
                'currency' => $currency,
                'updateUrl' => $updateUrl,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
        ];
    }
}
