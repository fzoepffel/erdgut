<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_is_redirected_away_from_guest_auth_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/auth/login')
            ->assertRedirect('/app/dashboard');

        $this->actingAs($user)
            ->get('/auth/register')
            ->assertRedirect('/app/dashboard');
    }
}
