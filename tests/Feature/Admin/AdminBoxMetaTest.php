<?php

namespace Tests\Feature\Admin;

use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBoxMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_next_update_expected_at(): void
    {
        $admin = User::factory()->admin()->create();
        $box = Box::factory()->create();

        $when = now()->copy()->addDays(2)->setTime(10, 0);

        $this->actingAs($admin)
            ->patch(route('app.admin.boxes.meta.update', $box), [
                'next_update_expected_at' => $when->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('app.admin.boxes.show', $box))
            ->assertSessionHas('status', 'box-meta-updated');

        $box->refresh();
        $this->assertNotNull($box->next_update_expected_at);
        $this->assertSame(
            $when->format('Y-m-d H:i'),
            $box->next_update_expected_at->format('Y-m-d H:i')
        );
    }

    public function test_non_admin_cannot_update_meta(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $box = Box::factory()->create();

        $this->actingAs($user)
            ->patch(route('app.admin.boxes.meta.update', $box), [
                'next_update_expected_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_clear_next_update_expected_at(): void
    {
        $admin = User::factory()->admin()->create();
        $box = Box::factory()->create(['next_update_expected_at' => now()->addDays(3)]);

        $this->actingAs($admin)
            ->patch(route('app.admin.boxes.meta.update', $box), [
                'next_update_expected_at' => '',
            ])
            ->assertRedirect(route('app.admin.boxes.show', $box));

        $box->refresh();
        $this->assertNull($box->next_update_expected_at);
    }
}
