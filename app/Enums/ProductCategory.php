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
