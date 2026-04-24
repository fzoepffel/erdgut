<?php

namespace Tests\Feature\Admin;

use App\Enums\BoxStatus;
use App\Models\AuditEvent;
use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBoxEditOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_box_with_past_deadline_is_read_only_without_override(): void
    {
        $box = Box::factory()->create([
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDay(),
            'admin_edit_override_until' => null,
        ]);

        $this->assertFalse($box->allowsAdminLineEdits());
    }

    public function test_admin_can_open_edit_override_window(): void
    {
        $admin = User::factory()->admin()->create();
        $box = Box::factory()->create([
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDay(),
            'admin_edit_override_until' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('app.admin.boxes.override-edit', $box), [
                'minutes' => 45,
                'reason' => 'Customer phoned to swap an item post-deadline.',
            ]);

        $response->assertRedirect(route('app.admin.boxes.show', $box))
            ->assertSessionHas('status', 'box-edit-override-opened');

        $box->refresh();
        $this->assertNotNull($box->admin_edit_override_until);
        $this->assertTrue($box->admin_edit_override_until->isFuture());
        $this->assertTrue($box->allowsAdminLineEdits(),
            'Override must unlock admin line edits even for a locked box with expired deadline.');

        $event = AuditEvent::query()
            ->where('entity_type', 'box')
            ->where('entity_id', $box->id)
            ->where('action', 'admin_override_window')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame('Customer phoned to swap an item post-deadline.', $event->reason);
        $this->assertSame(45, (int) ($event->metadata['minutes'] ?? 0));
    }

    public function test_non_admin_cannot_open_override(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $box = Box::factory()->create([
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->post(route('app.admin.boxes.override-edit', $box))
            ->assertForbidden();

        $this->assertNull($box->fresh()->admin_edit_override_until);
    }

    public function test_guest_cannot_open_override(): void
    {
        $box = Box::factory()->create([
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDay(),
        ]);

        $this->post(route('app.admin.boxes.override-edit', $box))
            ->assertRedirect(route('auth.login'));
    }

    public function test_override_minutes_are_clamped(): void
    {
        $admin = User::factory()->admin()->create();
        $box = Box::factory()->create([
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.override-edit', $box), ['minutes' => 9999])
            ->assertSessionHasErrors('minutes');

        $this->assertNull($box->fresh()->admin_edit_override_until);
    }
}
