<?php

namespace App\Support;

use App\Services\SpargelOrderService;

/**
 * Canonical pickup-location registry shared by every anonymous sales
 * channel (the spargel trial-shop and the general shop).
 *
 * Each entry's array key is what we persist in the order's
 * `pickup_location` column; the value is the surface info (contact name +
 * address) shown on the confirmation screen, the email, and the admin
 * list. Keeping one source here means the spargel flow and the shop can
 * never drift to different location lists.
 *
 * The data lives in PHP (not config) so it can be referenced from a class
 * constant — see {@see SpargelOrderService::PICKUP_LOCATIONS}
 * — and so a typo'd `.env` can't silently empty the pickup list.
 */
final class PickupLocations
{
    /**
     * @var array<string, array{name:string, street:string, postal_code:string, city:string}>
     */
    public const LOCATIONS = [
        'karl_kunger' => [
            'name' => 'Arthur Hamcha',
            'street' => 'Karl-Kunger-Straße 9',
            'postal_code' => '12435',
            'city' => 'Berlin',
        ],
        'gotenstrasse' => [
            'name' => 'Milan Ross',
            'street' => 'Gotenstraße 19',
            'postal_code' => '10829',
            'city' => 'Berlin',
        ],
    ];

    /**
     * @return array<string, array{name:string, street:string, postal_code:string, city:string}>
     */
    public static function all(): array
    {
        return self::LOCATIONS;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::LOCATIONS);
    }

    /**
     * Look up a pickup location by its key. Returns null for an unknown
     * key (defensive against a stale persisted value).
     *
     * @return array{name:string, street:string, postal_code:string, city:string}|null
     */
    public static function find(?string $key): ?array
    {
        if ($key === null || ! isset(self::LOCATIONS[$key])) {
            return null;
        }

        return self::LOCATIONS[$key];
    }
}
