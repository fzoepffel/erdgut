<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Mail\SpargelOrderPaidAdminNotification;
use App\Models\SpargelOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SpargelOrderPaidAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_dispatched_to_every_admin_when_order_transitions_to_paid(): void
    {
        Mail::fake();

        $admin1 = User::factory()->admin()->create(['email' => 'a1@example.test']);
        $admin2 = User::factory()->admin()->create(['email' => 'a2@example.test']);
        User::factory()->create(['is_admin' => false, 'email' => 'customer@example.test']);

        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $order->forceFill([
            'status' => SpargelOrderStatus::Paid,
            'paid_at' => now(),
        ])->save();

        Mail::assertSent(SpargelOrderPaidAdminNotification::class, 2);
        Mail::assertSent(SpargelOrderPaidAdminNotification::class, function ($mail) use ($admin1) {
            return $mail->hasTo($admin1->email);
        });
        Mail::assertSent(SpargelOrderPaidAdminNotification::class, function ($mail) use ($admin2) {
            return $mail->hasTo($admin2->email);
        });
        Mail::assertNotSent(SpargelOrderPaidAdminNotification::class, fn ($mail) => $mail->hasTo('customer@example.test'));
    }

    public function test_no_paid_email_when_status_change_is_not_to_paid(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $order->forceFill(['status' => SpargelOrderStatus::Failed])->save();

        // Opened-notification is expected to fire on creation; the test
        // only asserts the paid follow-up does NOT fire on a non-paid edge.
        Mail::assertNotSent(SpargelOrderPaidAdminNotification::class);
    }

    public function test_no_paid_email_when_paid_order_is_updated_again(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::Paid,
        ]);

        $order->forceFill(['fulfilled_at' => now()])->save();

        // Re-saving an already-paid order should not re-fire the paid email.
        Mail::assertNotSent(SpargelOrderPaidAdminNotification::class);
    }

    public function test_email_subject_includes_order_id_and_total(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
            'total_amount_eur' => 18.50,
        ]);
        $order->forceFill(['status' => SpargelOrderStatus::Paid])->save();

        Mail::assertSent(SpargelOrderPaidAdminNotification::class, function ($mail) use ($order) {
            $envelope = $mail->envelope();

            return str_contains($envelope->subject, '#'.$order->id)
                && str_contains($envelope->subject, '18.50');
        });
    }

    public function test_no_admin_users_means_no_send_and_no_error(): void
    {
        Mail::fake();
        User::factory()->create(['is_admin' => false]);

        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
        ]);
        $order->forceFill(['status' => SpargelOrderStatus::Paid])->save();

        Mail::assertNothingSent();
    }
}
