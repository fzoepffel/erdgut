<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Snapshot of everything the dashboard view needs to render. Pre-computed
 * by DashboardStateResolver so the view doesn't perform queries.
 */
final class DashboardState
{
    /**
     * @param  Collection<int, array{delivery: Delivery, box: ?Box, deadline: ?Carbon, canModifyBox: bool}>  $deliveryCards
     * @param  Collection<int, Box>  $upcomingSubscriptionBoxes
     */
    public function __construct(
        public readonly DashboardKind $kind,
        public readonly ?Subscription $subscription,
        public readonly ?Order $latestTrialOrder,
        public readonly ?Box $trialBox,
        public readonly ?Box $currentSubscriptionBox,
        public readonly Collection $upcomingSubscriptionBoxes,
        public readonly Collection $deliveryCards,
    ) {}
}
