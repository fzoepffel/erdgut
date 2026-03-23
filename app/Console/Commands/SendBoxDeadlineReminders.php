<?php

namespace App\Console\Commands;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Notifications\BoxDeadlineReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBoxDeadlineReminders extends Command
{
    protected $signature = 'notifications:box-deadlines';

    protected $description = 'Send reminder emails for editable boxes with deadline tomorrow';

    public function handle(): int
    {
        $boxes = Box::query()
            ->where('status', BoxStatus::Editable)
            ->whereDate('modification_deadline_at', Carbon::tomorrow())
            ->with('user')
            ->get();

        foreach ($boxes as $box) {
            if ($box->user !== null) {
                $box->user->notify(new BoxDeadlineReminderNotification($box));
            }
        }

        $this->info("Sent {$boxes->count()} box deadline reminder(s).");

        return self::SUCCESS;
    }
}
