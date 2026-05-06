<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $delivery = Delivery::factory()->create();

        $this->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $delivery = Delivery::factory()->create();

        $this->actingAs($user)
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertForbidden();
    }

    public function test_admin_can_mark_delivery_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        // J9 (UX notes): the orphan-box guard now blocks delivered without
        // composed contents. This test cares about the status transition
        // itself, not box-content rules — pass confirm_empty=1 to keep the
        // contract focused.
        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(
                route('app.admin.deliveries.status.update', $delivery),
                ['status' => 'delivered', 'confirm_empty' => '1']
            )
            ->assertRedirect(route('app.admin.deliveries.index'))
            ->assertSessionHas('status', 'admin-delivery-status-updated');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
    }

    public function test_marking_delivery_in_transit_stamps_in_transit_at(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Scheduled]);
        $this->assertNull($delivery->in_transit_at);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'in_transit']);

        $delivery->refresh();
        $this->assertNotNull($delivery->in_transit_at, 'in_transit_at must be stamped on the in_transit transition');
        $this->assertNull($delivery->delivered_at, 'delivered_at should not be stamped yet');
    }

    public function test_marking_delivery_delivered_stamps_both_timestamps_when_skipping_in_transit(): void
    {
        // Self-pickup customers might go straight from scheduled → delivered
        // without ever being marked in_transit. Backfill both for a clean record.
        // confirm_empty=1 to bypass the J9 guard since this test is about
        // timestamp backfill, not orphan-box rules.
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Scheduled]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(
                route('app.admin.deliveries.status.update', $delivery),
                ['status' => 'delivered', 'confirm_empty' => '1']
            );

        $delivery->refresh();
        $this->assertNotNull($delivery->in_transit_at, 'in_transit_at backfilled even when skipped directly to delivered');
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_status_timestamps_are_stamp_once_only(): void
    {
        // Flipping status away from delivered and back must not overwrite the
        // canonical delivered_at — the original timestamp is what support cares about.
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Scheduled]);

        // First: in_transit → records timestamp.
        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'in_transit']);
        $first = $delivery->fresh()->in_transit_at;
        $this->assertNotNull($first);

        // Re-flip back to scheduled, then forward to in_transit again.
        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'scheduled']);
        sleep(1); // make sure now() would differ
        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'in_transit']);

        $this->assertEquals($first->toDateTimeString(), $delivery->fresh()->in_transit_at->toDateTimeString(),
            'in_transit_at must not be reset on re-transition');
    }

    public function test_admin_can_mark_delivery_skipped(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'skipped'])
            ->assertRedirect(route('app.admin.deliveries.index'));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Skipped, $delivery->status);
    }

    public function test_admin_cannot_set_invalid_status(): void
    {
        $admin = User::factory()->admin()->create();
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'not-a-status'])
            ->assertSessionHasErrors('status');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
    }
}
