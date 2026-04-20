<?php

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SyncDeliveriesFromBoxesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_missing_delivery_for_future_box_week(): void
    {
        $user = User::factory()->create();
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);

        $monday = Carbon::parse('2027-01-04');
        Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $monday->toDateString(),
        ]);

        $this->assertSame(0, Delivery::query()->where('user_id', $user->id)->count());

        $this->artisan('deliveries:sync-from-boxes', ['--from' => $monday->toDateString()])
            ->assertSuccessful();

        $expectedShip = $monday->copy()->addDays(2)->toDateString();

        $this->assertTrue(Delivery::query()
            ->where('user_id', $user->id)
            ->where('is_weekly_override', false)
            ->whereDate('scheduled_for_date', $expectedShip)
            ->where('status', DeliveryStatus::Scheduled)
            ->exists());
    }
}
