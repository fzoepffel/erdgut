<?php

namespace Tests\Feature\StressFlows;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Mail\DeliveredMail;
use App\Mail\DeliveryInTransitMail;
use App\Models\Delivery;
use App\Models\User;
use App\Services\Onboarding\TrialOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Stress-flow 1 (ERD-342): Trial signup → pay → in-transit → delivered.
 *
 * Chains the full lifecycle: order created as pending, marked paid,
 * delivery synced, admin transitions status through InTransit → Delivered.
 * Asserts outcomes (DB state + mail dispatch), not status codes.
 */
class Flow01TrialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_order_reaches_delivered_state_with_expected_mails(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'trial@example.com']);
        $admin = User::factory()->admin()->create();

        $order = app(TrialOrderService::class)->createOrder($user, [
            'box_size' => 'medium',
            'preferences' => [],
            'allergies' => [],
        ]);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);

        app(TrialOrderService::class)->markOrderPaid($order);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'status' => DeliveryStatus::Scheduled,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'in_transit'])
            ->assertRedirect();
        Mail::assertQueued(DeliveryInTransitMail::class);

        // J9 (UX notes): the orphan-box guard on Delivered now requires
        // either composed box contents or an explicit confirm_empty=1.
        // This stress flow doesn't compose a Box (the trial-order
        // composition path is exercised in box-setup tests) so we
        // acknowledge the empty box explicitly here.
        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(
                route('app.admin.deliveries.status.update', $delivery),
                ['status' => 'delivered', 'confirm_empty' => '1']
            )
            ->assertRedirect();
        Mail::assertQueued(DeliveredMail::class);

        $this->assertSame(DeliveryStatus::Delivered->value, $delivery->fresh()->status->value);
    }
}
