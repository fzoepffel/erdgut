<?php

namespace Tests\Feature\Spargel;

use App\Enums\SpargelOrderStatus;
use App\Mail\SpargelOrderOpenedAdminNotification;
use App\Mail\SpargelOrderPaidAdminNotification;
use App\Models\SpargelOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SpargelOrderOpenedAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_dispatched_to_every_admin_when_a_new_order_is_created(): void
    {
        Mail::fake();

        $admin1 = User::factory()->admin()->create(['email' => 'a1@example.test']);
        $admin2 = User::factory()->admin()->create(['email' => 'a2@example.test']);
        User::factory()->create(['is_admin' => false, 'email' => 'customer@example.test']);

        SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        Mail::assertSent(SpargelOrderOpenedAdminNotification::class, 2);
        Mail::assertSent(SpargelOrderOpenedAdminNotification::class, fn ($m) => $m->hasTo($admin1->email));
        Mail::assertSent(SpargelOrderOpenedAdminNotification::class, fn ($m) => $m->hasTo($admin2->email));
        Mail::assertNotSent(SpargelOrderOpenedAdminNotification::class, fn ($m) => $m->hasTo('customer@example.test'));
    }

    public function test_creation_email_does_not_double_with_paid_email(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        $order = SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        Mail::assertSent(SpargelOrderOpenedAdminNotification::class, 1);
        Mail::assertNotSent(SpargelOrderPaidAdminNotification::class);

        // Now flip to paid — paid email should fire, opened email should NOT re-fire.
        $order->forceFill(['status' => SpargelOrderStatus::Paid])->save();

        Mail::assertSent(SpargelOrderOpenedAdminNotification::class, 1);
        Mail::assertSent(SpargelOrderPaidAdminNotification::class, 1);
    }

    public function test_subject_marks_in_person_vs_sumup(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        SpargelOrder::factory()->create([
            'status' => SpargelOrderStatus::PendingPayment,
            'payment_method' => 'in_person',
            'total_amount_eur' => 12.00,
        ]);

        Mail::assertSent(SpargelOrderOpenedAdminNotification::class, function ($mail) {
            $env = $mail->envelope();

            return str_contains($env->subject, 'vor Ort') && str_contains($env->subject, '12,00');
        });
    }
}
