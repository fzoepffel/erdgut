<?php

namespace App\Enums;

/**
 * Optional display word for Piece products: replaces "Stück" in every
 * customer-facing and document rendering (a pot of basil, a bag of
 * flour). Pure presentation: the canonical unit stays 'piece' and the
 * step/cap math is untouched.
 */
enum CountUnitLabel: string
{
    case Pot = 'pot';
    case Bag = 'bag';
    case Jar = 'jar';
    case Bottle = 'bottle';
    case Pack = 'pack';
    case Tray = 'tray';
    case Net = 'net';
    case Crate = 'crate';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelKey(): string
    {
        return 'units.'.$this->value;
    }

    /** The correctly pluralized word (word only, no count). */
    public function label(int|float $count = 1, ?string $locale = null): string
    {
        return trans_choice($this->labelKey(), $count, [], $locale ?? app()->getLocale());
    }
}
