<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Vegetables = 'vegetables';
    case Fruit = 'fruit';
    case Dairy = 'dairy';
    case Bakery = 'bakery';
    case Fish = 'fish';
    case Meat = 'meat';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Cases ordered alphabetically by their localized admin label (de/en/fr).
     * Use this for any UI dropdown — enum declaration order is product-meaningful
     * but not find-able for an admin scanning a list.
     *
     * @return list<self>
     */
    public static function casesSortedByLabel(): array
    {
        $cases = self::cases();
        $collator = class_exists(\Collator::class) ? new \Collator(app()->getLocale()) : null;

        usort($cases, function (self $a, self $b) use ($collator): int {
            $la = (string) __('admin.category.'.$a->value);
            $lb = (string) __('admin.category.'.$b->value);

            return $collator
                ? ($collator->compare($la, $lb) ?: 0)
                : strcasecmp($la, $lb);
        });

        return $cases;
    }

    /**
     * @return list<string>
     */
    public static function valuesSortedByLabel(): array
    {
        return array_map(fn (self $c): string => $c->value, self::casesSortedByLabel());
    }

    /**
     * The visual "market stall" this category belongs to.
     * Drives the color system.
     */
    public function stall(): string
    {
        return match ($this) {
            self::Vegetables, self::Fruit => 'produce',
            self::Dairy => 'dairy',
            self::Bakery => 'bakery',
            self::Fish => 'fish',
            self::Meat => 'meat',
        };
    }
}
