<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserProfileFieldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_includes_nullable_profile_contact_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'phone',
            'profile_preferences',
        ]));
    }

    public function test_user_can_persist_optional_profile_contact_fields(): void
    {
        $preferences = [
            'delivery_contact_method' => 'sms',
            'leave_at_door' => true,
        ];

        $user = User::factory()->create([
            'phone' => '+49 151 12345678',
            'profile_preferences' => $preferences,
        ]);

        $user->refresh();

        $this->assertSame('+49 151 12345678', $user->phone);
        $this->assertSame($preferences, $user->profile_preferences);
    }
}
