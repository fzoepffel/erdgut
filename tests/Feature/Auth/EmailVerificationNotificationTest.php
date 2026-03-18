<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_request_a_new_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post('/auth/email/verification-notification');

        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_is_redirected_to_dashboard_when_requesting_verification_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/auth/email/verification-notification');

        $response->assertRedirect(route('app.dashboard', absolute: false));
    }

    public function test_verification_notification_route_is_throttled(): void
    {
        $user = User::factory()->unverified()->create();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)
                ->post('/auth/email/verification-notification')
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->post('/auth/email/verification-notification')
            ->assertStatus(429);
    }
}
