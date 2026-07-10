<?php

namespace Tests\Feature\Marketing;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_page_renders(): void
    {
        $response = $this->get(route('public.contact'));

        $response->assertOk();
        $response->assertSee('We’re here to help with subscriptions, deliveries, and partnerships.', false);
    }

    public function test_valid_contact_submission_persists_and_sends_notification_email(): void
    {
        Mail::fake();

        $response = $this->post(route('public.contact.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Question',
            'message' => 'Can you deliver to Kreuzberg?',
            'website' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('public.contact', absolute: false));
        $response->assertSessionHas('status', 'contact-submitted');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Question',
            'message' => 'Can you deliver to Kreuzberg?',
        ]);

        Mail::assertQueued(ContactMessageReceived::class, 1);
    }

    public function test_invalid_contact_submission_returns_validation_errors(): void
    {
        Mail::fake();

        $response = $this->from(route('public.contact'))
            ->post(route('public.contact.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'message' => '',
                'website' => '',
            ]);

        $response->assertRedirect(route('public.contact', absolute: false));
        $response->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, ContactMessage::query()->count());
        Mail::assertNothingSent();
    }

    public function test_honeypot_submission_is_blocked_without_persisting_or_sending_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('public.contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'spam@example.com',
            'subject' => 'Spam Subject',
            'message' => 'Spam message body',
            'website' => 'https://bot.example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('public.contact', absolute: false));
        $response->assertSessionHas('status', 'contact-submitted');

        $this->assertSame(0, ContactMessage::query()->count());
        Mail::assertNothingSent();
    }
}
