<?php

namespace App\Support;

use App\Enums\Allergen;

/**
 * Normalises allergy form input so handlers get a consistent shape.
 *
 * Since ERD-346 the canonical allergies column holds a list of
 * {@see Allergen} enum values. A parallel free-text `allergies_note`
 * column is kept for anything the enum does not cover.
 *
 * Older forms submitted a CSV string; onboarding drafts and the admin
 * flows submit arrays of strings. This helper accepts any of those and
 * returns only valid enum values, dropping unknown entries so that
 * {@see WeeklyDistributionService} never sees invalid
 * allergen strings.
 */
final class AllergyInputNormaliser
{
    /**
     * Coerce whatever the request submitted (CSV string, array of strings, null)
     * into a list of trimmed strings. Invalid entries are NOT dropped here —
     * validation rules ({@see Rule::enum}) decide what is allowed.
     *
     * @return list<string>
     */
    public static function toArray(mixed $input): array
    {
        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        }

        if (! is_array($input)) {
            return [];
        }

        $result = [];
        $seen = [];
        foreach ($input as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $result[] = $value;
        }

        return $result;
    }

    /**
     * Keep only values that correspond to {@see Allergen} cases.
     *
     * @return list<string>
     */
    public static function toEnumList(mixed $input): array
    {
        $allowed = array_flip(Allergen::values());

        return array_values(array_filter(
            self::toArray($input),
            fn (string $value): bool => isset($allowed[$value]),
        ));
    }

    public static function normaliseNote(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $trimmed = trim($input);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 500);
    }
}
