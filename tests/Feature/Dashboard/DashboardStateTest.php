<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Dashboard\DashboardKind;
use App\Support\Dashboard\DashboardStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the 10 customer states the dashboard differentiates so each
 * user sees only the boxes/CTAs that apply to their actual situation.
 */
class DashboardStateTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): DashboardStateResolver
    {
        return $this->app->make(DashboardStateResolver::class);
    }

    public function test_state_1_new_visitor_with_no_purchase_at_all(): void
    {
        $user = User::factory()->create();
        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::NewVisitor, $state->kind);
        $this->assertNull($state->subscription);
        $this->assertNull($state->latestTrialOrder);
        $this->assertNull($state->trialBox);
        $this->assertNull($state->currentSubscriptionBox);
        $this->assertCount(0, $state->upcomingSubscriptionBoxes);
        $this->assertCount(0, $state->deliveryCards);
        $this->assertFalse($state->kind->showsSubscriptionBoxes());
        $this->assertFalse($state->kind->showsTrialBlock());
    }

    public function test_state_2_trial_setup_started_but_not_finished(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::PendingPayment,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::TrialPending, $state->kind);
        $this->assertTrue($state->kind->showsResumeTrialSetup());
        $this->assertFalse($state->kind->showsSubscriptionBoxes());
        $this->assertFalse($state->kind->showsTrialBlock());
    }

    public function test_state_3a_trial_paid_but_not_yet_delivered(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'scheduled_for_date' => now()->addWeek()->toDateString(),
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::TrialOnTheWay, $state->kind);
        $this->assertTrue($state->kind->showsTrialBlock());
        $this->assertFalse($state->kind->showsSubscriptionBoxes());
    }

    public function test_state_3b_trial_delivered(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Delivered,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::TrialDelivered, $state->kind);
        $this->assertTrue($state->kind->showsTrialBlock());
    }

    public function test_state_4a_subscription_pending_with_paid_trial(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionPendingWithTrial, $state->kind);
        $this->assertTrue($state->kind->showsTrialBlock());
        $this->assertTrue($state->kind->showsResumeSubscriptionSetup());
        $this->assertFalse($state->kind->showsSubscriptionBoxes());
    }

    public function test_state_4b_subscription_pending_no_trial(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionPendingNoTrial, $state->kind);
        $this->assertFalse($state->kind->showsTrialBlock());
        $this->assertTrue($state->kind->showsResumeSubscriptionSetup());
        $this->assertFalse($state->kind->showsSubscriptionBoxes());
    }

    public function test_state_5a_subscription_active_first_week_no_box_yet(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionFirstWeek, $state->kind);
        $this->assertTrue($state->kind->showsSubscriptionBoxes());
        $this->assertFalse($state->kind->showsTrialBlock());
        $this->assertNull($state->currentSubscriptionBox);
    }

    public function test_state_5b_subscription_active_with_composed_boxes(): void
    {
        $user = User::factory()->create();
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
        ]);
        Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionActive, $state->kind);
        $this->assertTrue($state->kind->showsSubscriptionBoxes());
    }

    public function test_state_6_subscription_paused(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Paused,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionPaused, $state->kind);
        $this->assertTrue($state->kind->showsSubscriptionBoxes());
    }

    public function test_state_7_subscription_cancelled(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Cancelled,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionCancelled, $state->kind);
        $this->assertFalse($state->kind->showsSubscriptionBoxes());
    }

    public function test_trial_box_does_not_leak_into_subscription_box_panels(): void
    {
        // Reproduces the bug the user reported: trial.tester had a paid trial
        // Order with a Box (linked via order_id, not subscription_id), plus a
        // PendingPayment subscription. The dashboard was showing the trial box
        // as if it were the "current week subscription box" because the queries
        // pulled all boxes for the user.
        $user = User::factory()->create();
        $trialOrder = Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
        ]);
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);
        // The trial box: linked to the Order, NOT to the subscription.
        Box::factory()->create([
            'user_id' => $user->id,
            'order_id' => $trialOrder->id,
            'subscription_id' => null,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        $state = $this->resolver()->resolve($user);

        $this->assertSame(DashboardKind::SubscriptionPendingWithTrial, $state->kind);
        // Subscription box panels stay empty: a trial box must not render as
        // "your weekly subscription box".
        $this->assertNull($state->currentSubscriptionBox);
        $this->assertCount(0, $state->upcomingSubscriptionBoxes);
        // But the trial box IS surfaced in its own slot for the trial block.
        $this->assertNotNull($state->trialBox);
    }

    public function test_dashboard_for_trial_only_user_shows_trial_block_not_subscription_panels(): void
    {
        $user = User::factory()->create();
        $trialOrder = Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
        ]);
        Box::factory()->create([
            'user_id' => $user->id,
            'order_id' => $trialOrder->id,
            'subscription_id' => null,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="dashboard-trial-block"', $html);
        // Subscription box panels (Current week / Almanach grid) must NOT
        // render for trial-only users.
        $this->assertStringNotContainsString('feed.current_box', $html);
        $this->assertStringNotContainsString('feed.almanach', $html);
    }

    public function test_dashboard_for_pending_payment_user_shows_resume_block(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'box_size' => 'medium',
            'sumup_token' => null,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="dashboard-resume-subscription-setup"', $html);
        $this->assertStringNotContainsString('feed.current_box', $html);
        $this->assertStringNotContainsString('feed.almanach', $html);
    }

    public function test_dashboard_for_active_subscriber_shows_subscription_panels(): void
    {
        $user = User::factory()->create();
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
        ]);
        // One past-anchored "current" box and one future box to populate both
        // the "Current week box" and "Almanach upcoming" panels.
        Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => now()->subDays(2)->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
        ]);
        Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        // Bottom-rail cleanup: the legacy "Current week box" card (and its
        // feed.current_box link) is gone — the manifest grid covers items
        // and the almanac covers upcoming weeks. The manifest can be empty
        // when there are no box items AND no portfolio products to fall
        // back on (this test seeds neither), so we only assert on the
        // almanac which always renders four rows for steady-state users.
        $this->assertStringContainsString('customer-almanac-rows', $html);
        $this->assertStringContainsString('customer-dashboard-hero', $html);
        $this->assertStringNotContainsString('feed.current_box', $html);
        $this->assertStringNotContainsString('feed.almanach.box_row', $html);
        $this->assertStringNotContainsString('data-testid="dashboard-trial-block"', $html);
        $this->assertStringNotContainsString('data-testid="dashboard-resume-subscription-setup"', $html);
    }
}
