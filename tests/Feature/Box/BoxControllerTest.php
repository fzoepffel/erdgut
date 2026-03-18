<?php

namespace Tests\Feature\Box;

use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_box_placeholder_page(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk()
            ->assertSee('Box editing details are coming soon.');
    }

    public function test_non_owner_cannot_open_box_placeholder_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($otherUser)
            ->get(route('app.boxes.show', $box))
            ->assertForbidden();
    }
}
