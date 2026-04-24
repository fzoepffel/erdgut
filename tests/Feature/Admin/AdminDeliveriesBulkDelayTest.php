<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryStatus;
use App\Models\AuditEvent;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDeliveriesBulkDelayTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_bulk_delay(): void
    {
        $this->post(route('app.admin.deliveries.bulk-delay'), [
            'date' => Carbon::tomorrow()->toDateString(),
            'delta_days' => 2,
        ])->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_cannot_bulk_delay(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('app.admin.deliveries.bulk-delay'), [
                'date' => Carbon::tomorrow()->toDateString(),
                'delta_days' => 2,
            ])->assertForbidden();
    }

    public function test_admin_shifts_all_open_deliveries_on_date_and_audits_each(): void
    {
        $admin = User::factory()->admin()->create();
        $date = Carbon::tomorrow();

        $a = Delivery::factory()->create([
            'scheduled_for_date' => $date->toDateString(),
            'status' => DeliveryStatus::Scheduled,
        ]);
        $b = Delivery::factory()->create([
            'scheduled_for_date' => $date->toDateString(),
            'status' => DeliveryStatus::Scheduled,
        ]);
        $delivered = Delivery::factory()->create([
            'scheduled_for_date' => $date->toDateString(),
            'status' => DeliveryStatus::Delivered,
        ]);
        $otherDay = Delivery::factory()->create([
            'scheduled_for_date' => $date->copy()->addDay()->toDateString(),
            'status' => DeliveryStatus::Scheduled,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('app.admin.deliveries.bulk-delay'), [
                'date' => $date->toDateString(),
                'delta_days' => 3,
                'reason' => 'Driver out sick.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'admin-deliveries-bulk-delayed');
        $response->assertSessionHas('admin_deliveries_bulk_count', 2);

        $expectedNewDate = $date->copy()->addDays(3)->toDateString();
        $this->assertSame($expectedNewDate, $a->fresh()->scheduled_for_date->toDateString());
        $this->assertSame($expectedNewDate, $b->fresh()->scheduled_for_date->toDateString());

        // Delivered + other-day deliveries must NOT be touched.
        $this->assertSame($date->toDateString(), $delivered->fresh()->scheduled_for_date->toDateString());
        $this->assertSame($date->copy()->addDay()->toDateString(), $otherDay->fresh()->scheduled_for_date->toDateString());

        $events = AuditEvent::query()
            ->where('entity_type', 'delivery')
            ->where('action', 'admin_bulk_delay')
            ->whereIn('entity_id', [$a->id, $b->id])
            ->get();

        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame($admin->id, $event->actor_user_id);
            $this->assertSame('Driver out sick.', $event->reason);
            $this->assertSame(3, (int) ($event->metadata['delta_days'] ?? 0));
            $this->assertSame($date->toDateString(), $event->metadata['before']['scheduled_for_date'] ?? null);
            $this->assertSame($expectedNewDate, $event->metadata['after']['scheduled_for_date'] ?? null);
        }
    }

    public function test_validation_rejects_zero_or_negative_delta(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->post(route('app.admin.deliveries.bulk-delay'), [
                'date' => Carbon::tomorrow()->toDateString(),
                'delta_days' => 0,
            ])->assertSessionHasErrors('delta_days');
    }
}
