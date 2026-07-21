<?php

namespace App\Console\Commands;

use App\Services\Shop\ShopReducedOfferService;
use Illuminate\Console\Command;

/**
 * Emails the reduced-offer waitlist when a window auto-reopens (the admin
 * "open now" action notifies immediately instead). Idempotent: each reduced
 * cycle notifies at most once, tracked on the period row.
 */
class NotifyOfferReopen extends Command
{
    protected $signature = 'shop:notify-offer-reopen';

    protected $description = 'Notify the reduced-offer waitlist when the full shop offer returns';

    public function handle(ShopReducedOfferService $reducedOffer): int
    {
        $count = $reducedOffer->processDueReopens();

        $this->info("Processed {$count} reduced-offer reopen(s).");

        return self::SUCCESS;
    }
}
