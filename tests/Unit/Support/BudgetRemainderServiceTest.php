<?php

namespace Tests\Unit\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BudgetRemainderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetRemainderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_remainder_returns_positive_when_under_budget(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'status' => SubscriptionStatus::Active,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'estimated_value' => 20.00,
        ]);

        $remainder = app(BudgetRemainderService::class)->getRemainder($user, $box);

        $this->assertSame(15.0, $remainder);
    }

    public function test_get_remainder_returns_negative_when_over_budget(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'small',
            'status' => SubscriptionStatus::Active,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'estimated_value' => 30.00,
        ]);

        $remainder = app(BudgetRemainderService::class)->getRemainder($user, $box);

        $this->assertSame(-10.0, $remainder);
    }

    public function test_get_remainder_returns_null_without_subscription(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'estimated_value' => 20.00,
        ]);

        $remainder = app(BudgetRemainderService::class)->getRemainder($user, $box);

        $this->assertNull($remainder);
    }

    public function test_get_remainder_uses_correct_box_size(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'large',
            'status' => SubscriptionStatus::Active,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'estimated_value' => 40.00,
        ]);

        $remainder = app(BudgetRemainderService::class)->getRemainder($user, $box);

        $this->assertSame(2.0, $remainder);
    }
}
