<?php

namespace App\Enums;

/**
 * Dietary categorisation for products.
 *
 * Used to auto-filter composition against subscriber preferences
 * (e.g. a vegetarian subscriber's box excludes products tagged
 * contains_meat / contains_fish).
 */
enum DietaryTag: string
{
    case Vegan = 'vegan';
    case Vegetarian = 'vegetarian';
    case ContainsMeat = 'contains_meat';
    case ContainsFish = 'contains_fish';
    case ContainsDairy = 'contains_dairy';
    case ContainsEggs = 'contains_eggs';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
