<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for the shop's availability computation.
 *
 * The availability matrix is identical for every anonymous visitor and only
 * changes when admin edits inventory or an order moves stock. Rather than
 * enumerate and forget individual keys on every write, we embed a monotonic
 * integer *version* in each cache key: bumping the version instantly orphans
 * every previously-cached entry (they simply expire on their own TTL).
 *
 * A short TTL is layered on top as a safety net so any missed invalidation
 * self-heals within {@see TTL_SECONDS} — important on the file cache driver
 * used in production (IONOS), where we can't rely on tag-based flushing.
 */
class InventoryCache
{
    private const VERSION_KEY = 'inventory.cache.version';

    /** Safety-net TTL (seconds) bounding staleness if a bump is ever missed. */
    public const TTL_SECONDS = 120;

    /** Current cache generation; defaults to 1 on a cold store. */
    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Advance the generation, invalidating every key built from the old one.
     * Re-stores (rather than `increment`) so a missing key still advances.
     */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    /** Version-scoped cache key for the given logical suffix. */
    public static function key(string $suffix): string
    {
        return 'inventory.v'.self::version().'.'.$suffix;
    }

    /**
     * Remember a value under a version-scoped key with the safety-net TTL.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $suffix, Closure $callback): mixed
    {
        return Cache::remember(self::key($suffix), self::TTL_SECONDS, $callback);
    }
}
