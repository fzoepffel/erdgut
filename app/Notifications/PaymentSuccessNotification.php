<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
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
        $date = $this->payment->paid_at?->translatedFormat('d. F Y') ?? now()->translatedFormat('d. F Y');
        $reference = (string) ($this->payment->subscription_id ?? $this->payment->id);

        return (new MailMessage)
            ->subject(__('Zahlung erfolgreich') . ' – ' . config('app.name'))
            ->view('emails.payment-success', [
                'amount' => $amount,
                'currency' => $currency,
                'date' => $date,
                'subscriptionReference' => $reference,
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
            'subscription_id' => $this->payment->subscription_id,
        ];
    }
}
