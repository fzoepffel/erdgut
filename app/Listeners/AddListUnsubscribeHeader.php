<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

class AddListUnsubscribeHeader
{
    public function handle(MessageSending $event): void
    {
        $mailbox = config('mail.unsubscribe_mailbox');

        if (empty($mailbox)) {
            return;
        }

        $headers = $event->message->getHeaders();

        if ($headers->has('List-Unsubscribe')) {
            return;
        }

        $headers->addTextHeader('List-Unsubscribe', '<mailto:'.$mailbox.'?subject=unsubscribe>');
    }
}
