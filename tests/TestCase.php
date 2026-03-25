<?php

namespace Tests;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Active subscription with SumUp token (passes subscriber.full routes and nav).
     */
    protected function subscribeUserFully(User $user): Subscription
    {
        return Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
        ]);
    }
}
