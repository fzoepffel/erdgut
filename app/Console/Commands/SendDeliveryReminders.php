<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Notifications\DeliveryReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDeliveryReminders extends Command
{
    protected $signature = 'notifications:delivery-reminders';

    protected $description = 'Send reminder emails for deliveries scheduled tomorrow';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $deliveries = Delivery::query()
            ->whereDate('scheduled_for_date', $tomorrow)
            ->where('status', DeliveryStatus::Scheduled)
            ->with(['user', 'address'])
            ->get();

        foreach ($deliveries as $delivery) {
            if ($delivery->user !== null) {
                $delivery->user->notify(new DeliveryReminderNotification($delivery));
            }
        }

        $this->info("Sent {$deliveries->count()} delivery reminder(s) for {$tomorrow}.");

        return self::SUCCESS;
    }
}
