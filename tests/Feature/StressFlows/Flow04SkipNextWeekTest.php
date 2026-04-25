<?php

namespace Tests\Feature\StressFlows;

use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionSkipConfirmationMail;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionSkipService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Stress-flow 4 (ERD-342): Skip a single upcoming week. Outcome: a single
 * subscription_skip_weeks row is materialized and the customer receives a
 * confirmation mail.
 */
class Flow04SkipNextWeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_skipping_next_week_creates_one_skip_row_and_emails_user(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $monday = CarbonImmutable::today()->startOfWeek()->addWeek()->toDateString();

        app(SubscriptionSkipService::class)
            ->syncUserSkipWeeks($subscription, [$monday], $user->id);

        $this->assertSame(1, $subscription->skipWeeks()->count());
        $this->assertSame(
            $monday,
            $subscription->skipWeeks()->first()->week_start_date->toDateString()
        );

        Mail::assertQueued(SubscriptionSkipConfirmationMail::class);
    }
}
