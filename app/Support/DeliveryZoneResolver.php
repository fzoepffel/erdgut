<?php

namespace App\Support;

class DeliveryZoneResolver
{
    /**
     * Resolve the zone key a given user falls into.
     *
     * Today every user is in the single default zone. Once the zone model
     * exists, this method will look up the zone based on postal code.
     */
    public function resolveZoneKey(?int $userId = null): string
    {
        return (string) config('delivery.default_zone', 'berlin_core');
    }

    /**
     * @return list<int>
     */
    public function allowedWeekdays(string $zoneKey): array
    {
        return array_values(array_map('intval', (array) config("delivery.zones.$zoneKey.allowed_weekdays", [])));
    }

    /**
     * @return list<string>
     */
    public function allowedTimeslots(string $zoneKey): array
    {
        return array_values(array_map('strval', (array) config("delivery.zones.$zoneKey.allowed_timeslots", [])));
    }
}
