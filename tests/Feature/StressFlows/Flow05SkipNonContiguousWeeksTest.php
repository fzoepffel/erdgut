<?php

namespace Tests\Feature\StressFlows;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionSkipService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 5 (ERD-342): Skip three non-contiguous weeks. Outcome: each
 * week materializes as its own skip row; a contiguous-only model would
 * collapse them, so the assertion proves non-contiguity is preserved.
 */
class Flow05SkipNonContiguousWeeksTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_non_contiguous_weeks_persist_as_three_skip_rows(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $base = CarbonImmutable::today()->startOfWeek();
        $weeks = [
            $base->addWeeks(1)->toDateString(),
            $base->addWeeks(3)->toDateString(),
            $base->addWeeks(6)->toDateString(),
        ];

        app(SubscriptionSkipService::class)
            ->syncUserSkipWeeks($subscription, $weeks, $user->id);

        $this->assertSame(3, $subscription->skipWeeks()->count());

        $persisted = $subscription->skipWeeks()
            ->orderBy('week_start_date')
            ->get()
            ->map(fn ($s) => $s->week_start_date->toDateString())
            ->all();

        sort($weeks);
        $this->assertSame($weeks, $persisted);
    }
}
