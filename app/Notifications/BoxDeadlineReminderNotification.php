<?php

namespace App\Notifications;

use App\Models\Box;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BoxDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Box $box
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
        $deadline = $this->box->modification_deadline_at?->translatedFormat('l, d. F Y, H:i') ?? '';
        $weekStart = $this->box->week_start_date?->translatedFormat('d. F Y') ?? '';
        $editUrl = route('app.boxes.show', $this->box);

        return (new MailMessage)
            ->subject(__('Erinnerung: Box-Änderungsfrist endet morgen') . ' – ' . config('app.name'))
            ->view('emails.box-deadline-reminder', [
                'deadline' => $deadline,
                'weekStart' => $weekStart,
                'editUrl' => $editUrl,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'box_id' => $this->box->id,
            'week_start_date' => $this->box->week_start_date?->toDateString(),
        ];
    }
}
