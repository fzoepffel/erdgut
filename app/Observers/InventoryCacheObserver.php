<?php

namespace App\Observers;

use App\Providers\AppServiceProvider;
use App\Services\Shop\ShopOrderService;
use App\Support\InventoryCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates the cached availability matrix whenever a model that feeds into
 * inventory math changes. Attached to inflows, schedules, shop/spargel orders
 * and their items, products, and box items (see {@see AppServiceProvider}).
 *
 * Any persisted change — create, update, or delete — bumps the cache version,
 * so the next storefront request recomputes from fresh data. This is what keeps
 * a just-sold-out product from lingering as "available" until the TTL lapses;
 * the authoritative oversell guard remains the row-locked re-check in
 * {@see ShopOrderService::createOrder()}.
 */
class InventoryCacheObserver
{
    public function saved(Model $model): void
    {
        InventoryCache::bump();
    }

    public function deleted(Model $model): void
    {
        InventoryCache::bump();
    }
}
