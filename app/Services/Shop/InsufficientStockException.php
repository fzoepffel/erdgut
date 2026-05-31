<?php

namespace App\Services\Shop;

use App\Models\Product;
use RuntimeException;

/**
 * Thrown by {@see ShopOrderService::createOrder} when a cart line asks
 * for more of a product than remains available on the chosen delivery
 * date — checked race-tightly inside the persist transaction so two
 * concurrent orders can't both clear the same remaining stock.
 *
 * Carries the offending product and the requested/remaining quantities
 * (canonical units) so the controller can build a localized message
 * pointing the customer at exactly which line to reduce.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly float $requested,
        public readonly float $remaining,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product #%d: requested %.4f, remaining %.4f.',
            $product->id,
            $requested,
            $remaining,
        ));
    }
}
