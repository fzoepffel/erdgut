<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOwnershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_access_own_user_resource(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.users.show', $user));

        $response->assertNoContent();
    }

    public function test_user_cannot_access_another_user_resource(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($authenticatedUser)->get(route('account.users.show', $otherUser));

        $response->assertForbidden();
    }
}
