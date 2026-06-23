<?php

namespace App\Enums;

use App\Support\Money;
use InvalidArgumentException;

/**
 * The kind of unit a product is measured in. Locked to four cases so
 * admins can't introduce drift (the freeform predecessor caused real
 * display drift in the box-setup view).
 *
 *  - `mass`   → canonical storage in grams; admin entry/display in g, 100 g, or kg.
 *  - `volume` → canonical storage in millilitres; admin entry/display in ml or L.
 *  - `piece`  → integer count; no sub-units.
 *  - `bunch`  → integer count; no sub-units.
 *
 * Quantities live in the database in their *canonical* unit. The
 * per-product `price_display_unit` (a string on `products`) remembers
 * which symbol the admin chose for entry/display. The kind itself
 * stays magnitude-agnostic — that's the formatter's job.
 */
enum ProductUnit: string
{
    case Mass = 'mass';
    case Volume = 'volume';
    case Piece = 'piece';
    case Bunch = 'bunch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Short label for the *kind* (mass / volume / piece / bunch),
     * localized via `lang/{de,en,fr}.json` under `units.{value}`.
     */
    public function label(?string $locale = null): string
    {
        return (string) __('units.'.$this->value, [], $locale ?? app()->getLocale());
    }

    /**
     * The canonical storage symbol for this kind. Quantities in the DB
     * are in this unit.
     */
    public function canonicalSymbol(): string
    {
        return match ($this) {
            self::Mass => 'g',
            self::Volume => 'ml',
            self::Piece => 'piece',
            self::Bunch => 'bunch',
        };
    }

    /**
     * The display symbols the admin can pick for this kind. For mass +
     * volume this is the small/large pair; for piece + bunch the only
     * option is the kind itself.
     *
     * @return list<string>
     */
    public function allowedDisplayUnits(): array
    {
        return match ($this) {
            self::Mass => ['g', '100g', 'kg'],
            self::Volume => ['ml', 'l'],
            self::Piece => ['piece'],
            self::Bunch => ['bunch'],
        };
    }

    /**
     * Default display unit when none was chosen. Defaults to the
     * everyday-natural reading (kg, L) for mass/volume.
     */
    public function defaultDisplayUnit(): string
    {
        return match ($this) {
            self::Mass => 'kg',
            self::Volume => 'l',
            self::Piece => 'piece',
            self::Bunch => 'bunch',
        };
    }

    /**
     * Conversion factor: how many canonical units make one of the
     * given display unit. Used to convert between admin-entered prices
     * / quantities and the canonical DB representation.
     *
     * Throws if the display unit isn't allowed for this kind.
     */
    public function canonicalUnitsPerDisplay(string $displayUnit): int
    {
        if (! in_array($displayUnit, $this->allowedDisplayUnits(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Display unit [%s] is not valid for product unit [%s].',
                $displayUnit,
                $this->value,
            ));
        }

        return match ($displayUnit) {
            'kg', 'l' => 1000,
            '100g' => 100,
            default => 1,
        };
    }

    /**
     * Render a quantity (in canonical units of this kind) for display.
     * For mass/volume the threshold is 1000 — below it stays in g/ml,
     * at or above it switches to kg/L with one decimal (trailing zero
     * stripped). Piece + bunch are always whole counts.
     *
     * Decimal formatting follows German conventions on `de` locale and
     * `.` for thousands in `en`/`fr`.
     */
    public function formatQuantity(int|float $canonical, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        [$decimalSep, $thousandsSep] = Money::separators($locale);

        if ($this === self::Piece || $this === self::Bunch) {
            $count = (int) round((float) $canonical);
            $label = (string) __('units.'.$this->value, [], $locale);

            return number_format($count, 0, $decimalSep, $thousandsSep).' '.$label;
        }

        $value = (float) $canonical;
        $smallSymbol = $this->canonicalSymbol(); // g or ml
        $largeSymbol = $this === self::Mass ? 'kg' : 'l';

        if (abs($value) < 1000) {
            return number_format($value, 0, $decimalSep, $thousandsSep).' '.$smallSymbol;
        }

        $large = $value / 1000;
        // Render up to 2 decimals (10 g / 10 ml granularity) and strip
        // trailing zeros. Clean values stay short ("1 kg", "1,5 kg") while
        // adjacent 50 g steps stay distinct ("1,05 kg" vs "1,1 kg") instead
        // of both rounding to the same "1,1 kg".
        $formatted = number_format($large, 2, $decimalSep, $thousandsSep);
        if (str_contains($formatted, $decimalSep)) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, $decimalSep);
        }

        return $formatted.' '.self::displaySymbol($largeSymbol);
    }

    /**
     * Render a price (stored per canonical unit) in the given display
     * unit, e.g. `21,50 €/kg`.
     */
    public function formatPrice(float $pricePerCanonical, string $displayUnit, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        [$decimalSep, $thousandsSep] = Money::separators($locale);

        $pricePerDisplay = $pricePerCanonical * $this->canonicalUnitsPerDisplay($displayUnit);

        return number_format($pricePerDisplay, 2, $decimalSep, $thousandsSep)
            .' €/'.self::displaySymbol($displayUnit);
    }

    /**
     * The user-facing symbol for a display unit. Uppercases litres so
     * the lowercase enum value `l` doesn't read as the digit `1`.
     */
    public static function displaySymbol(string $displayUnit): string
    {
        return match ($displayUnit) {
            'l' => 'L',
            '100g' => '100 g',
            'g', 'kg', 'ml' => $displayUnit,
            default => (string) __('units.'.$displayUnit, [], app()->getLocale()),
        };
    }
}
