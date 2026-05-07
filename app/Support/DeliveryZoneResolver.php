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

    /**
     * Country code we currently deliver to (ISO-3166-1 alpha-2) for the
     * default zone. Address forms compare against this — addresses
     * outside this country are rejected. Falls back to 'DE' if config
     * doesn't pin one.
     */
    public function defaultZoneCountryCode(): string
    {
        $key = $this->resolveZoneKey();

        return strtoupper((string) config("delivery.zones.$key.country_code", 'DE'));
    }

    /**
     * Is the given postal code inside the default delivery zone? Returns
     * true when:
     *  - postal code is a 5-digit numeric string (German PLZ format),
     *  - postal code falls inside [postal_code_min, postal_code_max].
     *
     * If config doesn't define a min/max we pass-through (any 5-digit
     * code is accepted) so non-Berlin instances aren't accidentally
     * gated. Used by the B5 fix (Berlin geo-validation in onboarding).
     */
    public function isPostalCodeInDefaultZone(?string $postalCode): bool
    {
        if ($postalCode === null) {
            return false;
        }
        $trimmed = trim($postalCode);
        if (! preg_match('/^\d{5}$/', $trimmed)) {
            return false;
        }
        $key = $this->resolveZoneKey();
        $min = config("delivery.zones.$key.postal_code_min");
        $max = config("delivery.zones.$key.postal_code_max");
        if ($min === null || $max === null) {
            return true;
        }
        $code = (int) $trimmed;

        return $code >= (int) $min && $code <= (int) $max;
    }

    /**
     * Friendly summary used in form-hint copy ("PLZ 10115–14199").
     * Returns null if no range is configured.
     */
    public function defaultZonePostalCodeHint(): ?string
    {
        $key = $this->resolveZoneKey();
        $min = config("delivery.zones.$key.postal_code_min");
        $max = config("delivery.zones.$key.postal_code_max");
        if ($min === null || $max === null) {
            return null;
        }

        return sprintf('%05d–%05d', (int) $min, (int) $max);
    }
}
