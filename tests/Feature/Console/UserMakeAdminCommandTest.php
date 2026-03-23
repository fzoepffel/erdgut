<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fails_when_email_not_found(): void
    {
        $this->artisan('user:make-admin', ['email' => 'missing@example.com'])
            ->expectsOutputToContain('No user found')
            ->assertFailed();
    }

    public function test_command_grants_admin_and_is_idempotent(): void
    {
        $user = User::factory()->create([
            'email' => 'ops@example.com',
            'is_admin' => false,
        ]);

        $this->artisan('user:make-admin', ['email' => 'ops@example.com'])
            ->expectsOutputToContain('Admin access granted')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());

        $this->artisan('user:make-admin', ['email' => 'ops@example.com'])
            ->expectsOutputToContain('already an admin')
            ->assertSuccessful();
    }
}
