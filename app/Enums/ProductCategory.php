<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Vegetables = 'vegetables';
    case Fruit = 'fruit';
    case Dairy = 'dairy';
    case Bakery = 'bakery';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
