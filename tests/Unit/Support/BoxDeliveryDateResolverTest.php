<?php

namespace Tests\Unit\Support;

use App\Models\Address;
use App\Models\Box;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BoxDeliveryDateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BoxDeliveryDateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_to_subscription_delivery_weekday_for_a_known_box(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'delivery_weekday' => 5, // Friday
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => '2026-05-25', // Mon
        ]);

        $this->assertSame('2026-05-29', BoxDeliveryDateResolver::deliveryDate($box)->toDateString());
    }

    public function test_falls_back_to_zone_default_when_subscription_has_no_weekday(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'delivery_weekday' => null,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => '2026-05-25',
        ]);

        // Zone default is Wednesday (3).
        $this->assertSame('2026-05-27', BoxDeliveryDateResolver::deliveryDate($box)->toDateString());
    }

    public function test_for_week_helper_works_without_subscription(): void
    {
        $this->assertSame(
            '2026-05-27',
            BoxDeliveryDateResolver::deliveryDateForWeek(Carbon::parse('2026-05-25'))->toDateString(),
        );
    }

    public function test_sunday_weekday_lands_six_days_after_monday(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'delivery_weekday' => 0, // Sunday
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => '2026-05-25', // Mon
        ]);

        $this->assertSame('2026-05-31', BoxDeliveryDateResolver::deliveryDate($box)->toDateString());
    }
}
