<?php

namespace Tests\Feature\Mail;

use App\Listeners\AddListUnsubscribeHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ListUnsubscribeHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_injects_header_when_mailbox_configured(): void
    {
        config(['mail.unsubscribe_mailbox' => 'unsubscribe@erd-gut.de']);

        $email = new Email;
        $email->to('user@example.com')->subject('x')->text('x');

        (new AddListUnsubscribeHeader)->handle(new MessageSending($email));

        $header = $email->getHeaders()->get('List-Unsubscribe');

        $this->assertNotNull($header);
        $this->assertSame(
            '<mailto:unsubscribe@erd-gut.de?subject=unsubscribe>',
            $header->getBodyAsString()
        );
    }

    public function test_listener_is_noop_when_mailbox_not_configured(): void
    {
        config(['mail.unsubscribe_mailbox' => null]);

        $email = new Email;
        $email->to('user@example.com')->subject('x')->text('x');

        (new AddListUnsubscribeHeader)->handle(new MessageSending($email));

        $this->assertFalse($email->getHeaders()->has('List-Unsubscribe'));
    }

    public function test_listener_does_not_overwrite_existing_list_unsubscribe_header(): void
    {
        config(['mail.unsubscribe_mailbox' => 'unsubscribe@erd-gut.de']);

        $email = new Email;
        $email->to('user@example.com')->subject('x')->text('x');
        $email->getHeaders()->addTextHeader('List-Unsubscribe', '<mailto:custom@erd-gut.de>');

        (new AddListUnsubscribeHeader)->handle(new MessageSending($email));

        $this->assertSame(
            '<mailto:custom@erd-gut.de>',
            $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString()
        );
    }

    public function test_end_to_end_mail_dispatch_includes_list_unsubscribe_header(): void
    {
        config(['mail.unsubscribe_mailbox' => 'unsubscribe@erd-gut.de']);

        $sent = null;

        Event::listen(MessageSending::class, function ($event) use (&$sent): void {
            $sent = $event->message;
        });

        Mail::raw('ping', function ($message): void {
            $message->to('user@example.com')->subject('test');
        });

        $this->assertNotNull($sent);
        $header = $sent->getHeaders()->get('List-Unsubscribe');
        $this->assertNotNull($header);
        $this->assertStringContainsString('unsubscribe@erd-gut.de', $header->getBodyAsString());
    }
}
