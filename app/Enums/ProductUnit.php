<?php

namespace App\Enums;

/**
 * The unit a product is measured in. Locked to a fixed set so admins
 * can't type "kg" / "Kg" / "Kilogramm" inconsistently across products
 * and weekly stock rows (the freeform predecessor caused real display
 * drift in the box-setup view).
 *
 * Stored on `products.unit` as the raw enum value; localized display
 * label lives in `lang/{de,en,fr}.json` under `units.{value}`.
 */
enum ProductUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
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
     * Short display label in the given locale (defaults to current app locale).
     * Falls back to the raw enum value if the translation key is missing.
     */
    public function label(?string $locale = null): string
    {
        return (string) __('units.'.$this->value, [], $locale ?? app()->getLocale());
    }

    /**
     * The "natural" display unit for human-facing prices. Storage units
     * smaller than the natural unit (g → kg, ml → l) get aggregated for
     * display so customers see "21,50 €/kg" instead of "0,0215 €/g".
     */
    public function naturalUnit(): self
    {
        return match ($this) {
            self::Gram => self::Kilogram,
            self::Milliliter => self::Liter,
            default => $this,
        };
    }

    /**
     * Compact symbol for price displays: `kg`, `L`, `g`, `ml` for SI
     * units; the localized label for `piece` / `bunch`. Used in catalog
     * price strings like "21,50 €/kg" (we never write "21,50
     * €/Kilogramm" — convention is the abbreviation).
     */
    public function naturalDisplaySymbol(?string $locale = null): string
    {
        return match ($this) {
            self::Gram => 'g',
            self::Kilogram => 'kg',
            self::Milliliter => 'ml',
            self::Liter => 'L',
            default => $this->label($locale),
        };
    }

    /**
     * How many storage units make up one natural unit. 1000 for g↔kg
     * and ml↔l; 1 for everything else (the storage unit IS the natural
     * unit). Used both for the admin price-input conversion (natural →
     * storage) and the customer price display (storage → natural).
     */
    public function storageUnitsPerNaturalUnit(): int
    {
        return match ($this) {
            self::Gram, self::Milliliter => 1000,
            default => 1,
        };
    }
}
