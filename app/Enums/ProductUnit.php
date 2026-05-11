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
}
