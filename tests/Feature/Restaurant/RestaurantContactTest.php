<?php

namespace Tests\Feature\Restaurant;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\RestaurantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** B2B contact form: account-based sender, shared ContactMessage inbox. */
class RestaurantContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_gated_to_verified_restaurants(): void
    {
        $this->get(route('app.restaurant.contact'))->assertRedirect(route('auth.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('app.restaurant.contact'))->assertForbidden();

        $profile = RestaurantProfile::factory()->verified()->create();
        $this->actingAs($profile->user)
            ->get(route('app.restaurant.contact'))
            ->assertOk()
            ->assertSee($profile->business_name)
            ->assertSee($profile->user->email);
    }

    public function test_message_is_stored_with_account_identity_and_mailed(): void
    {
        Mail::fake();
        $profile = RestaurantProfile::factory()->verified()->create();

        $this->actingAs($profile->user)
            ->post(route('app.restaurant.contact.store'), [
                'subject' => 'Lieferfenster',
                'message' => 'Können wir donnerstags früher beliefert werden?',
            ])
            ->assertRedirect(route('app.restaurant.contact'))
            ->assertSessionHas('status', 'restaurant-contact-submitted');

        $stored = ContactMessage::query()->firstOrFail();
        $this->assertSame($profile->user->email, $stored->email);
        $this->assertStringContainsString($profile->business_name, $stored->name);
        $this->assertSame('[B2B] Lieferfenster', $stored->subject);
        $this->assertSame('Können wir donnerstags früher beliefert werden?', $stored->message);

        Mail::assertQueued(ContactMessageReceived::class);
    }

    public function test_message_is_required(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        $this->actingAs($profile->user)
            ->post(route('app.restaurant.contact.store'), ['message' => ''])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, ContactMessage::query()->count());
    }
}
