<?php

namespace Tests\Unit\Support;

use App\Models\Subscription;
use App\Support\BillingFulfillmentWeek;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingFulfillmentWeekTest extends TestCase
{
    public function test_week_start_uses_monday_of_week_containing_next_charge_date(): void
    {
        $subscription = new Subscription([
            'next_charge_date' => Carbon::parse('2025-06-18'),
        ]);

        $today = Carbon::parse('2025-06-20');
        $weekStart = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, $today);

        $this->assertSame('2025-06-16', $weekStart->toDateString());
    }

    public function test_week_start_uses_today_when_next_charge_date_null(): void
    {
        $subscription = new Subscription([
            'next_charge_date' => null,
        ]);

        $today = Carbon::parse('2025-06-18');
        $weekStart = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, $today);

        $this->assertSame('2025-06-16', $weekStart->toDateString());
    }
}
