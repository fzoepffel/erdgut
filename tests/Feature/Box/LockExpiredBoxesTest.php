<?php

namespace Tests\Feature\Box;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockExpiredBoxesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_eligible_boxes(): void
    {
        $user = User::factory()->create();
        Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->subHour(),
        ]);

        $this->artisan('boxes:lock', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 box(es) would be locked');
    }

    public function test_locks_eligible_expired_boxes(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->subHour(),
        ]);

        $this->artisan('boxes:lock')
            ->assertSuccessful()
            ->expectsOutputToContain('Locked 1 box(es)');

        $this->assertSame(BoxStatus::Locked, $box->fresh()->status);
    }

    public function test_skips_already_locked_boxes(): void
    {
        $user = User::factory()->create();
        Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subHour(),
        ]);

        $this->artisan('boxes:lock')
            ->assertSuccessful()
            ->expectsOutputToContain('No boxes to lock');
    }

    public function test_skips_future_deadline_boxes(): void
    {
        $user = User::factory()->create();
        Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->artisan('boxes:lock')
            ->assertSuccessful()
            ->expectsOutputToContain('No boxes to lock');
    }

    public function test_locks_multiple_eligible_boxes(): void
    {
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            Box::factory()->create([
                'user_id' => $user->id,
                'status' => BoxStatus::Editable,
                'modification_deadline_at' => now()->subHour(),
            ]);
        }

        $this->artisan('boxes:lock')
            ->assertSuccessful()
            ->expectsOutputToContain('Locked 3 box(es)');
    }

    public function test_no_boxes_outputs_message(): void
    {
        $this->artisan('boxes:lock')
            ->assertSuccessful()
            ->expectsOutputToContain('No boxes to lock');
    }
}
