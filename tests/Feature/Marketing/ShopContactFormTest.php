<?php

namespace Tests\Feature\Marketing;

use App\Mail\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_contact_page_renders_with_shop_chrome(): void
    {
        $response = $this->get(route('public.shop-contact'));

        $response->assertOk();
        $response->assertSee('data-testid="customer-header-shop"', false);
        $response->assertSee('data-testid="shop-contact-form"', false);
        // No account chrome on the shop surface.
        $response->assertDontSee('href="'.route('auth.register').'"', false);
    }

    public function test_valid_shop_contact_submission_persists_and_sends_notification_email(): void
    {
        Mail::fake();

        $response = $this->post(route('public.shop-contact.store'), [
            'name' => 'Jana Schmidt',
            'email' => 'jana@example.com',
            'subject' => 'Lieferung',
            'message' => 'Liefert ihr nach Kreuzberg?',
            'website' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('public.shop-contact', absolute: false));
        $response->assertSessionHas('status', 'contact-submitted');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jana Schmidt',
            'email' => 'jana@example.com',
            'subject' => 'Lieferung',
            'message' => 'Liefert ihr nach Kreuzberg?',
        ]);

        Mail::assertSent(ContactMessageReceived::class, 1);
    }

    public function test_invalid_shop_contact_submission_returns_validation_errors(): void
    {
        Mail::fake();

        $response = $this->from(route('public.shop-contact'))
            ->post(route('public.shop-contact.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'message' => '',
                'website' => '',
            ]);

        $response->assertRedirect(route('public.shop-contact', absolute: false));
        $response->assertSessionHasErrors(['name', 'email', 'message']);

        Mail::assertNothingSent();
    }

    public function test_shop_contact_honeypot_silently_drops_submission(): void
    {
        Mail::fake();

        $response = $this->post(route('public.shop-contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'spam@example.com',
            'message' => 'buy now',
            'website' => 'http://spam.example',
        ]);

        $response->assertRedirect(route('public.shop-contact', absolute: false));
        $this->assertDatabaseMissing('contact_messages', ['email' => 'spam@example.com']);
        Mail::assertNothingSent();
    }
}
