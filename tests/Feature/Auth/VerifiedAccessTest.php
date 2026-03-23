<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_redirected_to_email_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk();
    }

    public function test_unverified_user_can_access_verification_notice_page(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk();
    }

    public function test_guest_cannot_access_verification_notice(): void
    {
        $this->get(route('verification.notice'))
            ->assertRedirect('/auth/login');
    }
}
