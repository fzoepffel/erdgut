<?php

namespace Tests\Feature\Database;

use App\Models\Box;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxModelStatusAndQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_box_persists_with_user_week_and_deadline(): void
    {
        $user = User::factory()->create();

        $box = Box::create([
            'user_id' => $user->id,
            'week_start_date' => '2026-05-04',
            'status' => Box::STATUS_EDITABLE,
            'modification_deadline_at' => '2026-05-02 18:00:00',
            'estimated_value' => 34.50,
        ]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'user_id' => $user->id,
            'week_start_date' => '2026-05-04 00:00:00',
            'status' => Box::STATUS_EDITABLE,
            'modification_deadline_at' => '2026-05-02 18:00:00',
            'estimated_value' => 34.5,
        ]);
    }

    public function test_box_status_transitions_can_be_stored(): void
    {
        $box = Box::factory()->create([
            'status' => Box::STATUS_EDITABLE,
        ]);

        $box->lock();
        $this->assertSame(Box::STATUS_LOCKED, $box->fresh()->status);

        $box->deliver();
        $this->assertSame(Box::STATUS_DELIVERED, $box->fresh()->status);

        $box->markEditable();
        $this->assertSame(Box::STATUS_EDITABLE, $box->fresh()->status);
    }

    public function test_boxes_can_be_queried_by_user_and_week(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Box::factory()->create([
            'user_id' => $userA->id,
            'week_start_date' => '2026-05-04',
        ]);
        Box::factory()->create([
            'user_id' => $userA->id,
            'week_start_date' => '2026-05-11',
        ]);
        Box::factory()->create([
            'user_id' => $userB->id,
            'week_start_date' => '2026-05-04',
        ]);

        $results = Box::query()
            ->forUser($userA->id)
            ->forWeek('2026-05-04')
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame($userA->id, $results->first()->user_id);
        $this->assertSame('2026-05-04', $results->first()->week_start_date->format('Y-m-d'));
    }

    public function test_relationship_contracts_are_exposed(): void
    {
        $user = new User;
        $box = new Box;

        $this->assertInstanceOf(HasMany::class, $user->boxes());
        $this->assertInstanceOf(BelongsTo::class, $box->user());
    }
}
