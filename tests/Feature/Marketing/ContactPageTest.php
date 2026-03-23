<?php

namespace Tests\Feature\Marketing;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_renders_form_with_testids(): void
    {
        $response = $this->get(route('public.contact'));

        $response->assertOk();
        $response->assertSee(route('public.contact.store'), false);
        $response->assertSee('data-testid="contact-form"', false);
        $response->assertSee('data-testid="contact-submit"', false);
        $response->assertSee('Your details', false);
        $response->assertSee('Your message', false);
    }

    public function test_valid_contact_submission_persists_and_redirects_with_flash(): void
    {
        Mail::fake();
        config(['mail.contact_to' => 'inbox@example.test']);

        $response = $this->from(route('public.contact'))
            ->post(route('public.contact.store'), [
                'name' => 'Alex Customer',
                'email' => 'alex@example.test',
                'subject' => 'Delivery question',
                'message' => 'When is my box arriving?',
                'website' => '',
            ]);

        $response->assertRedirect(route('public.contact'));
        $response->assertSessionHas('status', 'contact-submitted');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Alex Customer',
            'email' => 'alex@example.test',
            'subject' => 'Delivery question',
            'message' => 'When is my box arriving?',
        ]);

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail): bool {
            return $mail->hasTo('inbox@example.test');
        });

        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('data-testid="contact-success"', false)
            ->assertSee('Message received', false)
            ->assertSee('one to two business days', false);
    }

    public function test_invalid_contact_submission_shows_validation_errors(): void
    {
        $response = $this->from(route('public.contact'))
            ->post(route('public.contact.store'), [
                'name' => 'Alex',
                'email' => 'not-an-email',
                'message' => '',
                'website' => '',
            ]);

        $response->assertRedirect(route('public.contact'));
        $response->assertSessionHasErrors(['email', 'message']);
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_honeypot_filled_does_not_persist_or_send_mail_but_shows_success(): void
    {
        Mail::fake();
        config(['mail.contact_to' => 'inbox@example.test']);

        $before = ContactMessage::query()->count();

        $response = $this->from(route('public.contact'))
            ->post(route('public.contact.store'), [
                'name' => 'Bot',
                'email' => 'bot@example.test',
                'message' => 'Spam',
                'website' => 'https://spam.example',
            ]);

        $response->assertRedirect(route('public.contact'));
        $response->assertSessionHas('status', 'contact-submitted');

        $this->assertSame($before, ContactMessage::query()->count());
        Mail::assertNothingSent();
    }
}
