<?php

namespace App\Enums;

/**
 * Which stock pool availability and order validation should use.
 *
 * Consumer — public storefront and admin private orders; only shared
 * (non-B2B-exclusive) inflow batches count.
 *
 * BusinessTrade — admin business orders; shared batches plus B2B-only
 * batches reserved for trade buyers.
 */
enum ShopStockAudience: string
{
    case Consumer = 'consumer';
    case BusinessTrade = 'business_trade';
}
